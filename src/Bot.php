<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Exceptions\BotNotInitializedException;
use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\User;

/**
 * Static facade for BotInstance. Call Bot::init($token) once at startup,
 * then use Bot::* anywhere in your code.
 */
class Bot
{
    private static ?BotInstance $instance = null;

    public static function init(string $token, array $config = []): BotInstance
    {
        static::$instance = new BotInstance($token, $config);
        return static::$instance;
    }

    public static function getInstance(): BotInstance
    {
        if (static::$instance === null) {
            throw new BotNotInitializedException();
        }
        return static::$instance;
    }

    /** Proxies any unrecognized static call to BotInstance → TelegramApi. */
    public static function __callStatic(string $method, array $args): mixed
    {
        return static::getInstance()->$method(...$args);
    }

    // -------------------------------------------------------------------------
    // Route registration — typed for IDE autocomplete
    // -------------------------------------------------------------------------

    public static function onCommand(string $command, callable|string $handler): BotInstance
    {
        return static::getInstance()->onCommand($command, $handler);
    }

    public static function onText(callable|string $handler): BotInstance
    {
        return static::getInstance()->onText($handler);
    }

    public static function onMessage(callable|string $handler): BotInstance
    {
        return static::getInstance()->onMessage($handler);
    }

    public static function onCallbackQuery(string|callable $patternOrHandler, callable|string|null $handler = null): BotInstance
    {
        return static::getInstance()->onCallbackQuery($patternOrHandler, $handler);
    }

    public static function onPhoto(callable|string $handler): BotInstance
    {
        return static::getInstance()->onPhoto($handler);
    }

    public static function onDocument(callable|string $handler): BotInstance
    {
        return static::getInstance()->onDocument($handler);
    }

    public static function onInlineQuery(callable|string $handler): BotInstance
    {
        return static::getInstance()->onInlineQuery($handler);
    }

    public static function onUpdate(callable|string $handler): BotInstance
    {
        return static::getInstance()->onUpdate($handler);
    }

    public static function use(callable|string $middleware): BotInstance
    {
        return static::getInstance()->use($middleware);
    }

    public static function run(): void
    {
        static::getInstance()->run();
    }

    // -------------------------------------------------------------------------
    // Telegram API — typed for IDE autocomplete
    // -------------------------------------------------------------------------

    public static function sendMessage(int|string $chatId, string $text, array $options = []): Message
    {
        return static::getInstance()->api()->sendMessage($chatId, $text, $options);
    }

    public static function sendPhoto(int|string $chatId, string $photo, array $options = []): Message
    {
        return static::getInstance()->api()->sendPhoto($chatId, $photo, $options);
    }

    public static function sendDocument(int|string $chatId, string $document, array $options = []): Message
    {
        return static::getInstance()->api()->sendDocument($chatId, $document, $options);
    }

    public static function sendAudio(int|string $chatId, string $audio, array $options = []): Message
    {
        return static::getInstance()->api()->sendAudio($chatId, $audio, $options);
    }

    public static function sendVideo(int|string $chatId, string $video, array $options = []): Message
    {
        return static::getInstance()->api()->sendVideo($chatId, $video, $options);
    }

    public static function sendSticker(int|string $chatId, string $sticker, array $options = []): Message
    {
        return static::getInstance()->api()->sendSticker($chatId, $sticker, $options);
    }

    public static function sendLocation(int|string $chatId, float $latitude, float $longitude, array $options = []): Message
    {
        return static::getInstance()->api()->sendLocation($chatId, $latitude, $longitude, $options);
    }

    public static function sendChatAction(int|string $chatId, string $action): bool
    {
        return static::getInstance()->api()->sendChatAction($chatId, $action);
    }

    public static function editMessageText(int|string $chatId, int $messageId, string $text, array $options = []): Message
    {
        return static::getInstance()->api()->editMessageText($chatId, $messageId, $text, $options);
    }

    public static function deleteMessage(int|string $chatId, int $messageId): bool
    {
        return static::getInstance()->api()->deleteMessage($chatId, $messageId);
    }

    public static function answerCallbackQuery(string $callbackQueryId, array $options = []): bool
    {
        return static::getInstance()->api()->answerCallbackQuery($callbackQueryId, $options);
    }

    public static function getMe(): User
    {
        return static::getInstance()->api()->getMe();
    }

    public static function setWebhook(string $url, array $options = []): bool
    {
        return static::getInstance()->api()->setWebhook($url, $options);
    }

    public static function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return static::getInstance()->api()->deleteWebhook($dropPendingUpdates);
    }

    public static function getWebhookInfo(): array
    {
        return static::getInstance()->api()->getWebhookInfo();
    }
}
