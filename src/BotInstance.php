<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\WebhookException;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Types\Update;

class BotInstance
{
    private TelegramApi $api;
    private Router $router;

    public function __construct(
        string $token,
        private array $config = [],
        ?HttpClientInterface $http = null,
    ) {
        $httpOptions = [];
        if (!empty($config['proxy'])) {
            $httpOptions['proxy'] = $config['proxy'];
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

    // -------------------------------------------------------------------------
    // Route registration — fluent, returns $this
    // -------------------------------------------------------------------------

    public function onCommand(string $command, callable|string $handler): static
    {
        $this->router->addRoute('command', $command, $handler);
        return $this;
    }

    public function onText(callable|string $handler): static
    {
        $this->router->addRoute('text', '*', $handler);
        return $this;
    }

    public function onMessage(callable|string $handler): static
    {
        $this->router->addRoute('message', '*', $handler);
        return $this;
    }

    public function onCallbackQuery(string|callable $patternOrHandler, callable|string|null $handler = null): static
    {
        if ($handler === null) {
            $this->router->addRoute('callback_query', '*', $patternOrHandler);
        } else {
            $this->router->addRoute('callback_query', (string) $patternOrHandler, $handler);
        }
        return $this;
    }

    public function onPhoto(callable|string $handler): static
    {
        $this->router->addRoute('photo', '*', $handler);
        return $this;
    }

    public function onDocument(callable|string $handler): static
    {
        $this->router->addRoute('document', '*', $handler);
        return $this;
    }

    public function onInlineQuery(callable|string $handler): static
    {
        $this->router->addRoute('inline_query', '*', $handler);
        return $this;
    }

    public function onChosenInlineResult(callable|string $handler): static
    {
        $this->router->addRoute('chosen_inline_result', '*', $handler);
        return $this;
    }

    public function onEditedMessage(callable|string $handler): static
    {
        $this->router->addRoute('edited_message', '*', $handler);
        return $this;
    }

    public function onChannelPost(callable|string $handler): static
    {
        $this->router->addRoute('channel_post', '*', $handler);
        return $this;
    }

    public function onPoll(callable|string $handler): static
    {
        $this->router->addRoute('poll', '*', $handler);
        return $this;
    }

    public function onPollAnswer(callable|string $handler): static
    {
        $this->router->addRoute('poll_answer', '*', $handler);
        return $this;
    }

    public function onMyChatMember(callable|string $handler): static
    {
        $this->router->addRoute('my_chat_member', '*', $handler);
        return $this;
    }

    public function onChatMember(callable|string $handler): static
    {
        $this->router->addRoute('chat_member', '*', $handler);
        return $this;
    }

    public function onChatJoinRequest(callable|string $handler): static
    {
        $this->router->addRoute('chat_join_request', '*', $handler);
        return $this;
    }

    public function onShippingQuery(callable|string $handler): static
    {
        $this->router->addRoute('shipping_query', '*', $handler);
        return $this;
    }

    public function onPreCheckoutQuery(callable|string $handler): static
    {
        $this->router->addRoute('pre_checkout_query', '*', $handler);
        return $this;
    }

    public function onUpdate(callable|string $handler): static
    {
        $this->router->addRoute('update', '*', $handler);
        return $this;
    }

    public function onBusinessConnection(callable|string $handler): static
    {
        $this->router->addRoute('business_connection', '*', $handler);
        return $this;
    }

    public function onBusinessMessage(callable|string $handler): static
    {
        $this->router->addRoute('business_message', '*', $handler);
        return $this;
    }

    public function onEditedBusinessMessage(callable|string $handler): static
    {
        $this->router->addRoute('edited_business_message', '*', $handler);
        return $this;
    }

    public function onDeletedBusinessMessages(callable|string $handler): static
    {
        $this->router->addRoute('deleted_business_messages', '*', $handler);
        return $this;
    }

    public function onGuestMessage(callable|string $handler): static
    {
        $this->router->addRoute('guest_message', '*', $handler);
        return $this;
    }

    public function onMessageReaction(callable|string $handler): static
    {
        $this->router->addRoute('message_reaction', '*', $handler);
        return $this;
    }

    public function onMessageReactionCount(callable|string $handler): static
    {
        $this->router->addRoute('message_reaction_count', '*', $handler);
        return $this;
    }

    public function onPurchasedPaidMedia(callable|string $handler): static
    {
        $this->router->addRoute('purchased_paid_media', '*', $handler);
        return $this;
    }

    public function onChatBoost(callable|string $handler): static
    {
        $this->router->addRoute('chat_boost', '*', $handler);
        return $this;
    }

    public function onRemovedChatBoost(callable|string $handler): static
    {
        $this->router->addRoute('removed_chat_boost', '*', $handler);
        return $this;
    }

    public function onManagedBot(callable|string $handler): static
    {
        $this->router->addRoute('managed_bot', '*', $handler);
        return $this;
    }

    public function onStep(string $step, callable|string $handler, array $types = ['text']): static
    {
        $this->router->addRoute('step', $step, $handler, $types);
        return $this;
    }

    public function loadHandlers(array|string $handlers): static
    {
        foreach ((array) $handlers as $handlerClass) {
            $handlerClass::register();
        }
        return $this;
    }

    public function use(callable|string $middleware): static
    {
        $this->router->addMiddleware($middleware);
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
            $userRepository = new \Devflow\TelegramBot\Database\UserRepository();
        }

        $this->router->dispatch($update, $this->api, $this->config, $userRepository);
    }

    public function runPolling(?callable $onError = null): never
    {
        $offset = 0;
        $userRepository = null;
        if ($this->config['database'] ?? false) {
            $userRepository = new \Devflow\TelegramBot\Database\UserRepository();
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
