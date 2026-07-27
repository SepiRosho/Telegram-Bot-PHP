<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\MissingTokenException;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Types\Update;

class BotInstance
{
    private TelegramApi $api;
    private Router $router;
    private ?string $username = null;

    /**
     * $token is nullable only so that the overwhelmingly common
     * `Bot::init(env('BOT_TOKEN'))` with an unset BOT_TOKEN reports what is
     * actually wrong, instead of PHP rejecting the null argument first with a
     * TypeError that names neither the token nor the .env file.
     */
    public function __construct(
        ?string $token,
        private array $config = [],
        ?HttpClientInterface $http = null,
    ) {
        if ($token === null || trim($token) === '') {
            throw new MissingTokenException();
        }

        $httpOptions = [];
        foreach (['proxy', 'timeout', 'max_retries', 'max_retry_after'] as $option) {
            if (isset($config[$option]) && $config[$option] !== null && $config[$option] !== '') {
                $httpOptions[$option] = $config[$option];
            }
        }
        $this->api = new TelegramApi($http ?? new HttpClient($token, $httpOptions));
        $this->router = new Router();

        if (!empty($config['lang_path'])) {
            Lang::setPath($config['lang_path']);
        }
        if (!empty($config['default_locale'])) {
            Lang::setDefaultLocale($config['default_locale']);
        }
    }

    public function api(): TelegramApi
    {
        return $this->api;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * The bot's @username, cached to avoid an uncached getMe() call per
     * request. Cached in bot_settings when the database is enabled (so it
     * survives across requests), otherwise only for this process's lifetime.
     */
    public function username(): string
    {
        if ($this->username !== null) {
            return $this->username;
        }

        $useDb = $this->config['database'] ?? false;

        if ($useDb) {
            $cached = \Devflow\TelegramBot\Database\Models\BotSetting::get('bot_username');
            if ($cached !== null) {
                return $this->username = $cached;
            }
        }

        $username = $this->api->getMe()->username ?? '';

        if ($useDb) {
            \Devflow\TelegramBot\Database\Models\BotSetting::set('bot_username', $username);
        }

        return $this->username = $username;
    }

    // -------------------------------------------------------------------------
    // Route registration — fluent, returns $this
    // -------------------------------------------------------------------------

    public function onCommand(string $command, callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('command', $command, $handler, middleware: $middleware);
        return $this;
    }

    /**
     * Match non-command text. With one argument the handler sees every text
     * message; with two, the first is a pattern — either a `*` wildcard glob
     * ('buy_*') or a full PCRE regex ('/^buy_\d+$/') — so routing on message
     * content no longer requires a hand-rolled match() inside one catch-all.
     *
     * Overloaded rather than typed `string $pattern` because a lone string
     * argument already means a HandlerInterface class name; this mirrors how
     * onCallbackQuery() has always disambiguated the same way.
     */
    public function onText(
        string|callable $patternOrHandler,
        callable|string|null $handler = null,
        array $middleware = [],
    ): static {
        if ($handler === null) {
            $this->router->addRoute('text', '*', $patternOrHandler, middleware: $middleware);
        } else {
            $this->router->addRoute('text', (string) $patternOrHandler, $handler, middleware: $middleware);
        }
        return $this;
    }

    public function onMessage(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('message', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onCallbackQuery(
        string|callable $patternOrHandler,
        callable|string|null $handler = null,
        array $middleware = [],
    ): static {
        if ($handler === null) {
            $this->router->addRoute('callback_query', '*', $patternOrHandler, middleware: $middleware);
        } else {
            $this->router->addRoute('callback_query', (string) $patternOrHandler, $handler, middleware: $middleware);
        }
        return $this;
    }

    public function onPhoto(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('photo', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onDocument(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('document', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onInlineQuery(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('inline_query', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onChosenInlineResult(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('chosen_inline_result', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onEditedMessage(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('edited_message', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onChannelPost(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('channel_post', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onPoll(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('poll', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onPollAnswer(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('poll_answer', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onMyChatMember(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('my_chat_member', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onChatMember(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('chat_member', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onChatJoinRequest(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('chat_join_request', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onShippingQuery(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('shipping_query', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onPreCheckoutQuery(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('pre_checkout_query', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onUpdate(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('update', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onBusinessConnection(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('business_connection', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onBusinessMessage(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('business_message', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onEditedBusinessMessage(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('edited_business_message', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onDeletedBusinessMessages(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('deleted_business_messages', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onGuestMessage(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('guest_message', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onMessageReaction(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('message_reaction', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onMessageReactionCount(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('message_reaction_count', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onPurchasedPaidMedia(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('purchased_paid_media', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onChatBoost(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('chat_boost', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onRemovedChatBoost(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('removed_chat_boost', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onManagedBot(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('managed_bot', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function onStep(string $step, callable|string $handler, array $types = ['text'], array $middleware = []): static
    {
        $this->router->addRoute('step', $step, $handler, $types, $middleware);
        return $this;
    }

    /** Fires for any command not handled by a registered onCommand() route. */
    public function onUnknownCommand(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('unknown_command', '*', $handler, middleware: $middleware);
        return $this;
    }

    public function loadHandlers(array|string $handlers): static
    {
        foreach ((array) $handlers as $handlerClass) {
            $handlerClass::register();
        }
        return $this;
    }

    public function use(callable|string|MiddlewareInterface $middleware): static
    {
        $this->router->addMiddleware($middleware);
        return $this;
    }

    /**
     * Register routes that accept different chat types than the bot's global
     * `allowed_chat_types` config — e.g. exposing one group command on a bot
     * that is otherwise private-only:
     *
     *   Bot::chatTypes(['group', 'supergroup'], function () {
     *       Bot::onCommand('stats', $handler);
     *   });
     *
     * Pass ['*'] to accept any chat type.
     */
    public function chatTypes(array $chatTypes, callable $register): static
    {
        $this->router->withChatTypes($chatTypes, $register);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function run(): void
    {
        $webhookSecret = $this->config['webhook_secret'] ?? null;
        if ($webhookSecret !== null) {
            $received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
            if (!hash_equals((string) $webhookSecret, $received)) {
                // Set the status before throwing: PHP's default uncaught-exception
                // handling otherwise surfaces this as a 500, not the more correct 403.
                http_response_code(403);
                throw new WebhookException('Invalid webhook secret token.');
            }
        }

        $input = file_get_contents('php://input');

        if (empty($input)) {
            throw new WebhookException('Empty webhook payload received.');
        }

        $data = json_decode($input, true);

        if (!is_array($data)) {
            throw new WebhookException('Invalid JSON in webhook payload.');
        }

        $update = Update::fromArray($data);

        $userRepository = null;
        if ($this->config['database'] ?? false) {
            $userRepository = new \Devflow\TelegramBot\Database\UserRepository($this->config);
        }

        $this->router->dispatch($update, $this->api, $this->config, $userRepository);
    }

    public function runPolling(?callable $onError = null): never
    {
        $offset = 0;
        $userRepository = null;
        if ($this->config['database'] ?? false) {
            $userRepository = new \Devflow\TelegramBot\Database\UserRepository($this->config);
        }

        while (true) {
            try {
                $params = ['timeout' => 30, 'limit' => 100];
                if ($offset > 0) {
                    $params['offset'] = $offset;
                }

                $updates = $this->api->getUpdates($params);

                foreach ($updates as $updateData) {
                    $update = Update::fromArray($updateData);
                    $this->router->dispatch($update, $this->api, $this->config, $userRepository);
                    $offset = $update->updateId + 1;
                }
            } catch (\Throwable $e) {
                if ($onError !== null) {
                    ($onError)($e);
                }
                sleep(5);
            }
        }
    }

    // Proxy any unknown call directly to TelegramApi
    public function __call(string $method, array $args): mixed
    {
        return $this->api->$method(...$args);
    }
}
