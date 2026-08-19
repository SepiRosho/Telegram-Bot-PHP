<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\MissingTokenException;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Middleware\MiddlewareInterface;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Support\Log;
use Devflow\TelegramBot\Types\Update;

class BotInstance
{
    /**
     * getUpdates failures that no amount of waiting will fix: 401/404 mean the
     * token is wrong or revoked, and 409 means a webhook is still registered
     * (Telegram refuses to serve getUpdates alongside one). All three need a
     * human, so polling stops and says so instead of looping.
     */
    private const POLLING_FATAL_CODES = [401, 404, 409];

    /** Seconds to wait after the 1st, 2nd, … consecutive getUpdates failure. */
    private const POLLING_BACKOFF = [1, 2, 5, 10, 30, 60];

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
        Lang::setAutoFallback((bool) ($config['lang_auto_fallback'] ?? false));
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

    public function onEditedChannelPost(callable|string $handler, array $middleware = []): static
    {
        $this->router->addRoute('edited_channel_post', '*', $handler, middleware: $middleware);
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

        if (!isset($data['update_id'])) {
            throw new WebhookException('Invalid update payload: missing update_id.');
        }

        $update = Update::fromArray($data);

        $userRepository = null;
        if ($this->config['database'] ?? false) {
            $userRepository = new \Devflow\TelegramBot\Database\UserRepository($this->config);
        }

        $this->dispatchWebhookUpdate($update, $userRepository);
    }

    /**
     * The webhook counterpart of the polling loop's per-update catch.
     *
     * A scaffolded project routes anything that escapes here into a "🔴 Bot
     * crash" alert to ADMIN_CHAT_ID, so without this every user who blocks the
     * bot pages the operator about it. On a custom entry point that doesn't
     * force HTTP 200, it's worse: Telegram redelivers whatever wasn't answered
     * with a 2xx, so the same update keeps arriving.
     *
     * Only what Telegram itself treats as routine is absorbed. A real bug
     * still reaches your error handler untouched.
     */
    private function dispatchWebhookUpdate(Update $update, ?object $userRepository): void
    {
        try {
            $this->router->dispatch($update, $this->api, $this->config, $userRepository);
        } catch (TelegramApiException $e) {
            if (!$e->isExpected()) {
                throw $e;
            }

            Log::save("Ignored expected Telegram error: {$e->getMessage()}", 'WARNING');
        }
    }

    /**
     * Long-polling loop.
     *
     * $onError is called as ($throwable, $retryInSeconds) — 0 meaning the loop
     * is moving straight on to the next update rather than backing off.
     *
     * $dropPending discards whatever queued up while the bot was down, the
     * polling equivalent of setWebhook's `drop_pending_updates`.
     *
     * Fetching and dispatching are deliberately separated. A failed *fetch* is
     * the loop's problem and earns a backoff; a failed *dispatch* belongs to
     * one update and must never stall the queue behind it.
     */
    public function runPolling(?callable $onError = null, bool $dropPending = false): never
    {
        $offset = $dropPending ? $this->dropPendingUpdates() : 0;
        $failures = 0;
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
                $failures = 0;
            } catch (\Throwable $e) {
                // Some failures never resolve by waiting. Retrying them prints
                // the same line every few seconds until someone kills the
                // process, which reads exactly like a hang.
                if ($e instanceof TelegramApiException
                    && in_array($e->telegramErrorCode(), self::POLLING_FATAL_CODES, true)) {
                    if ($onError !== null) {
                        ($onError)($e, 0);
                    }
                    throw $e;
                }

                $wait = $this->pollingBackoff(++$failures, $e);
                if ($onError !== null) {
                    ($onError)($e, $wait);
                }
                sleep($wait);
                continue;
            }

            foreach ($updates as $updateData) {
                $update = Update::fromArray($updateData);

                // Advance past this update *before* running it. Telegram keeps
                // redelivering everything above the last confirmed offset, so
                // if a handler throws — the user blocked the bot, a DB write
                // failed — the old code never reached the line below and asked
                // for the very same update again, forever. That is the "stuck
                // retrying every 5 seconds" loop: not a network problem, an
                // offset that could only advance on the happy path.
                $offset = max($offset, $update->updateId + 1);

                try {
                    $this->router->dispatch($update, $this->api, $this->config, $userRepository);
                } catch (\Throwable $e) {
                    // One update's failure is not the loop's failure. No sleep:
                    // nothing here is rate-limited, and the offset has already
                    // moved past the update that broke.
                    if ($onError !== null) {
                        ($onError)($e, 0);
                    }
                }
            }
        }
    }

    /**
     * Skip everything Telegram queued while the bot was offline, and return
     * the offset that starts from "now".
     *
     * A negative offset asks Telegram for the *last* update rather than the
     * oldest, so one call names the end of the backlog without downloading any
     * of it. Confirming past that id discards the rest — the same effect as
     * setWebhook's `drop_pending_updates`, without deleting a webhook the
     * caller may still want registered.
     *
     * Deliberately not wrapped in a retry: if this call fails, polling should
     * start from the backlog rather than silently process what it was told to
     * drop.
     */
    private function dropPendingUpdates(): int
    {
        $updates = $this->api->getUpdates(['offset' => -1, 'limit' => 1, 'timeout' => 0]);
        $last    = end($updates);

        return is_array($last) && isset($last['update_id'])
            ? (int) $last['update_id'] + 1
            : 0;
    }

    /**
     * How long to wait after a failed getUpdates. Climbs so a genuinely
     * unreachable network isn't hammered once a second, but caps low enough
     * that a bot notices the connection came back within a minute.
     */
    private function pollingBackoff(int $consecutiveFailures, \Throwable $e): int
    {
        // Telegram naming its own wait beats any schedule we could invent.
        if ($e instanceof TelegramApiException && ($retryAfter = $e->retryAfter()) !== null) {
            return max(1, $retryAfter);
        }

        $steps = self::POLLING_BACKOFF;

        return $steps[min($consecutiveFailures, count($steps)) - 1];
    }

    // Proxy any unknown call directly to TelegramApi
    public function __call(string $method, array $args): mixed
    {
        return $this->api->$method(...$args);
    }
}
