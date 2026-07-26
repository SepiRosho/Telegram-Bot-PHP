<?php

namespace Devflow\TelegramBot\Routing;

use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Handlers\HandlerInterface;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;
use Devflow\TelegramBot\Support\Log;
use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\Update;

class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function addRoute(
        string $type,
        string $pattern,
        callable|string $handler,
        array $types = ['text'],
        array $middleware = [],
    ): void {
        $this->routes[] = new Route($type, $pattern, $handler, $types, $middleware);
    }

    public function addMiddleware(callable|string|MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(Update $update, TelegramApi $api, array $config = [], ?object $userRepository = null): void
    {
        $ctx = null;
        $debug = $config['debug'] ?? false;

        foreach ($this->orderedRoutes($config) as $route) {
            // Non-step routes: check without context first (cheap)
            if ($route->type !== 'step' && !$this->matches($route, $update)) {
                continue;
            }

            // Lazy-create context on first match candidate
            if ($ctx === null) {
                $ctx = new Context($update, $api, $config);
                if ($userRepository !== null) {
                    $ctx->setUserRepository($userRepository);
                }
            }

            // Step routes need the loaded user to check step value
            if ($route->type === 'step' && !$this->matchesStep($route, $update, $ctx)) {
                continue;
            }

            if ($debug) {
                Log::save("Route matched: {$route->type} \"{$route->pattern}\" (update #{$update->updateId})", 'DEBUG');
            }

            $this->runWithMiddleware($ctx, $route->handler, $route->middleware);
            return;
        }

        if ($debug) {
            Log::save("No route matched update #{$update->updateId} (type: {$update->type()})", 'DEBUG');
        }

        $this->clearPendingCallback($update, $api, $config);
    }

    /**
     * Step routes are evaluated ahead of every other route type so a broad
     * onText()/onMessage() catch-all registered in an earlier handler group
     * can't swallow a mid-flow message — that failure mode is silent (the
     * wizard simply never advances, with no exception and no log line), and
     * it depends on nothing but the order two files happen to be listed in
     * Bot::loadHandlers(). Commands are unaffected: matchesStep() rejects
     * them outright, so /cancel still escapes an active flow.
     *
     * Set config 'step_routes_first' => false for flat registration order.
     */
    private function orderedRoutes(array $config): array
    {
        if (($config['step_routes_first'] ?? true) === false) {
            return $this->routes;
        }

        $steps = [];
        $rest  = [];

        foreach ($this->routes as $route) {
            if ($route->type === 'step') {
                $steps[] = $route;
            } else {
                $rest[] = $route;
            }
        }

        return $steps === [] ? $rest : [...$steps, ...$rest];
    }

    /**
     * Telegram spins the tap indicator on the user's client until the bot
     * answers a callback query, so an unrouted button — a keyboard's own
     * page-indicator, a stale message's buttons after a redeploy — otherwise
     * spins until it times out. Answering an already-expired query is not
     * worth failing the request over.
     */
    private function clearPendingCallback(Update $update, TelegramApi $api, array $config): void
    {
        if ($update->callbackQuery === null || ($config['auto_answer_callbacks'] ?? true) === false) {
            return;
        }

        try {
            $api->answerCallbackQuery($update->callbackQuery->id);
        } catch (\Throwable $e) {
            if ($config['debug'] ?? false) {
                Log::save("Could not auto-answer callback query: {$e->getMessage()}", 'DEBUG');
            }
        }
    }

    private function matches(Route $route, Update $update): bool
    {
        return match ($route->type) {
            'command'             => $this->matchesCommand($route->pattern, $update),
            'unknown_command'     => $this->matchesUnknownCommand($update),
            'text'                => $update->message?->text !== null && !$update->message->isCommand(),
            'message'             => $update->message !== null,
            'edited_message'      => $update->editedMessage !== null,
            'channel_post'        => $update->channelPost !== null,
            'callback_query'      => $update->callbackQuery !== null
                && $this->matchesPattern($route->pattern, $update->callbackQuery->data ?? ''),
            'photo'               => $update->message?->photo !== null,
            'document'            => $update->message?->document !== null,
            'inline_query'        => $update->inlineQuery !== null,
            'chosen_inline_result' => $update->chosenInlineResult !== null,
            'poll'                => $update->poll !== null,
            'poll_answer'         => $update->pollAnswer !== null,
            'my_chat_member'      => $update->myChatMember !== null,
            'chat_member'         => $update->chatMember !== null,
            'chat_join_request'   => $update->chatJoinRequest !== null,
            'shipping_query'      => $update->shippingQuery !== null,
            'pre_checkout_query'  => $update->preCheckoutQuery !== null,
            'business_connection' => $update->businessConnection !== null,
            'business_message'    => $update->businessMessage !== null,
            'edited_business_message' => $update->editedBusinessMessage !== null,
            'deleted_business_messages' => $update->deletedBusinessMessages !== null,
            'guest_message'       => $update->guestMessage !== null,
            'message_reaction'    => $update->messageReaction !== null,
            'message_reaction_count' => $update->messageReactionCount !== null,
            'purchased_paid_media' => $update->purchasedPaidMedia !== null,
            'chat_boost'          => $update->chatBoost !== null,
            'removed_chat_boost'  => $update->removedChatBoost !== null,
            'managed_bot'         => $update->managedBot !== null,
            'update'              => true,
            default               => false,
        };
    }

    private function matchesCommand(string $command, Update $update): bool
    {
        if ($update->message === null || !$update->message->isCommand()) {
            return false;
        }
        if ($command === '*') {
            return true;
        }
        return $update->message->command() === ltrim($command, '/');
    }

    /**
     * Matches a command that isn't handled by any registered onCommand()
     * route — computed against the full set of registered command names
     * regardless of registration order, so (unlike an onMessage catch-all)
     * where you register onUnknownCommand() relative to onCommand() calls
     * doesn't silently change behavior.
     */
    private function matchesUnknownCommand(Update $update): bool
    {
        if ($update->message === null || !$update->message->isCommand()) {
            return false;
        }

        $command = $update->message->command();

        return $command !== null && !in_array($command, $this->registeredCommandNames(), true);
    }

    private function registeredCommandNames(): array
    {
        $names = [];

        foreach ($this->routes as $route) {
            if ($route->type === 'command' && $route->pattern !== '*') {
                $names[] = ltrim($route->pattern, '/');
            }
        }

        return $names;
    }

    private function matchesStep(Route $route, Update $update, Context $ctx): bool
    {
        if ($update->message === null || $update->message->isCommand()) {
            return false;
        }
        if (!$this->messageMatchesTypes($update->message, $route->types)) {
            return false;
        }

        // A user with no active step is not in a flow, so no step route should
        // claim them — without this, onStep('*') would match everyone and,
        // now that step routes are evaluated first, swallow the whole bot.
        $step = (string) $ctx->step();
        if ($step === '') {
            return false;
        }

        return $this->matchesPattern($route->pattern, $step);
    }

    private function messageMatchesTypes(Message $message, array $types): bool
    {
        if (in_array('*', $types, true)) {
            return true;
        }

        foreach ($types as $type) {
            $matches = match ($type) {
                'text'       => $message->text !== null,
                'photo'      => $message->photo !== null,
                'document'   => $message->document !== null,
                'audio'      => $message->audio !== null,
                'video'      => $message->video !== null,
                'voice'      => $message->voice !== null,
                'video_note' => $message->videoNote !== null,
                'sticker'    => $message->sticker !== null,
                'animation'  => $message->animation !== null,
                'contact'    => $message->contact !== null,
                'location'   => $message->location !== null,
                'venue'      => $message->venue !== null,
                'dice'       => $message->dice !== null,
                default      => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $pattern, string $value): bool
    {
        if ($pattern === '*') {
            return true;
        }
        if (!str_contains($pattern, '*')) {
            return $pattern === $value;
        }
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $value);
    }

    /**
     * Global middleware (Bot::use) wraps route-scoped middleware, which wraps
     * the handler — so a global auth check still runs before a route's own
     * rate limiter, and neither has to know about the other.
     */
    private function runWithMiddleware(Context $ctx, callable|string $handler, array $routeMiddleware = []): void
    {
        $runner = fn(Context $ctx) => $this->callHandler($handler, $ctx);

        foreach (array_reverse([...$this->middlewares, ...$routeMiddleware]) as $middleware) {
            $next = $runner;
            $runner = fn(Context $ctx) => $this->callMiddleware($middleware, $ctx, $next);
        }

        $runner($ctx);
    }

    private function callHandler(callable|string $handler, Context $ctx): void
    {
        if (is_string($handler)) {
            $instance = new $handler();
            if ($instance instanceof HandlerInterface) {
                $instance->handle($ctx);
                return;
            }
        }

        ($handler)($ctx);
    }

    private function callMiddleware(callable|string|MiddlewareInterface $middleware, Context $ctx, callable $next): void
    {
        if (is_string($middleware) && class_exists($middleware)) {
            $middleware = new $middleware();
        }

        // MiddlewareInterface declares handle(), not __invoke(), so an
        // instance is not callable and has to be dispatched explicitly.
        if ($middleware instanceof MiddlewareInterface) {
            $middleware->handle($ctx, $next);
            return;
        }

        ($middleware)($ctx, $next);
    }
}
