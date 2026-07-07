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

    public static function onChosenInlineResult(callable|string $handler): BotInstance
    {
        return static::getInstance()->onChosenInlineResult($handler);
    }

    public static function onEditedMessage(callable|string $handler): BotInstance
    {
        return static::getInstance()->onEditedMessage($handler);
    }

    public static function onChannelPost(callable|string $handler): BotInstance
    {
        return static::getInstance()->onChannelPost($handler);
    }

    public static function onPoll(callable|string $handler): BotInstance
    {
        return static::getInstance()->onPoll($handler);
    }

    public static function onPollAnswer(callable|string $handler): BotInstance
    {
        return static::getInstance()->onPollAnswer($handler);
    }

    public static function onMyChatMember(callable|string $handler): BotInstance
    {
        return static::getInstance()->onMyChatMember($handler);
    }

    public static function onChatMember(callable|string $handler): BotInstance
    {
        return static::getInstance()->onChatMember($handler);
    }

    public static function onChatJoinRequest(callable|string $handler): BotInstance
    {
        return static::getInstance()->onChatJoinRequest($handler);
    }

    public static function onShippingQuery(callable|string $handler): BotInstance
    {
        return static::getInstance()->onShippingQuery($handler);
    }

    public static function onPreCheckoutQuery(callable|string $handler): BotInstance
    {
        return static::getInstance()->onPreCheckoutQuery($handler);
    }

    public static function onUpdate(callable|string $handler): BotInstance
    {
        return static::getInstance()->onUpdate($handler);
    }

    public static function onStep(string $step, callable|string $handler): BotInstance
    {
        return static::getInstance()->onStep($step, $handler);
    }

    public static function loadHandlers(array|string $handlers): BotInstance
    {
        return static::getInstance()->loadHandlers($handlers);
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

    public static function sendVenue(int|string $chatId, float $latitude, float $longitude, string $title, string $address, array $options = []): Message
    {
        return static::getInstance()->api()->sendVenue($chatId, $latitude, $longitude, $title, $address, $options);
    }

    public static function sendMediaGroup(int|string $chatId, array $media, array $options = []): array
    {
        return static::getInstance()->api()->sendMediaGroup($chatId, $media, $options);
    }

    public static function answerInlineQuery(string $inlineQueryId, array $results, array $options = []): bool
    {
        return static::getInstance()->api()->answerInlineQuery($inlineQueryId, $results, $options);
    }

    public static function getChat(int|string $chatId): array
    {
        return static::getInstance()->api()->getChat($chatId);
    }

    public static function getChatMember(int|string $chatId, int $userId): array
    {
        return static::getInstance()->api()->getChatMember($chatId, $userId);
    }

    public static function getChatMemberCount(int|string $chatId): int
    {
        return static::getInstance()->api()->getChatMemberCount($chatId);
    }

    public static function leaveChat(int|string $chatId): bool
    {
        return static::getInstance()->api()->leaveChat($chatId);
    }

    public static function exportChatInviteLink(int|string $chatId): string
    {
        return static::getInstance()->api()->exportChatInviteLink($chatId);
    }

    public static function createChatInviteLink(int|string $chatId, array $options = []): array
    {
        return static::getInstance()->api()->createChatInviteLink($chatId, $options);
    }

    public static function restrictChatMember(int|string $chatId, int $userId, array $permissions, array $options = []): bool
    {
        return static::getInstance()->api()->restrictChatMember($chatId, $userId, $permissions, $options);
    }

    public static function promoteChatMember(int|string $chatId, int $userId, array $options = []): bool
    {
        return static::getInstance()->api()->promoteChatMember($chatId, $userId, $options);
    }

    public static function getFile(string $fileId): array
    {
        return static::getInstance()->api()->getFile($fileId);
    }

    public static function setMyCommands(array $commands, array $options = []): bool
    {
        return static::getInstance()->api()->setMyCommands($commands, $options);
    }

    public static function getMyCommands(array $options = []): array
    {
        return static::getInstance()->api()->getMyCommands($options);
    }

    public static function deleteMyCommands(array $options = []): bool
    {
        return static::getInstance()->api()->deleteMyCommands($options);
    }

    // -------------------------------------------------------------------------
    // Polling
    // -------------------------------------------------------------------------

    public static function getUpdates(array $options = []): array
    {
        return static::getInstance()->api()->getUpdates($options);
    }

    public static function poll(?callable $onError = null): never
    {
        static::getInstance()->runPolling($onError);
    }

    // -------------------------------------------------------------------------
    // Additional media
    // -------------------------------------------------------------------------

    public static function sendVideoNote(int|string $chatId, string $videoNote, array $options = []): Message
    {
        return static::getInstance()->api()->sendVideoNote($chatId, $videoNote, $options);
    }

    public static function stopPoll(int|string $chatId, int $messageId, array $options = []): array
    {
        return static::getInstance()->api()->stopPoll($chatId, $messageId, $options);
    }

    // -------------------------------------------------------------------------
    // Chat administration — extended
    // -------------------------------------------------------------------------

    public static function setChatTitle(int|string $chatId, string $title): bool
    {
        return static::getInstance()->api()->setChatTitle($chatId, $title);
    }

    public static function setChatDescription(int|string $chatId, string $description = ''): bool
    {
        return static::getInstance()->api()->setChatDescription($chatId, $description);
    }

    public static function getChatAdministrators(int|string $chatId): array
    {
        return static::getInstance()->api()->getChatAdministrators($chatId);
    }

    public static function setChatPermissions(int|string $chatId, array $permissions, array $options = []): bool
    {
        return static::getInstance()->api()->setChatPermissions($chatId, $permissions, $options);
    }

    public static function revokeChatInviteLink(int|string $chatId, string $inviteLink): array
    {
        return static::getInstance()->api()->revokeChatInviteLink($chatId, $inviteLink);
    }

    public static function approveChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return static::getInstance()->api()->approveChatJoinRequest($chatId, $userId);
    }

    public static function declineChatJoinRequest(int|string $chatId, int $userId): bool
    {
        return static::getInstance()->api()->declineChatJoinRequest($chatId, $userId);
    }

    public static function unpinAllChatMessages(int|string $chatId): bool
    {
        return static::getInstance()->api()->unpinAllChatMessages($chatId);
    }

    // -------------------------------------------------------------------------
    // Bot profile
    // -------------------------------------------------------------------------

    public static function setMyName(string $name = '', array $options = []): bool
    {
        return static::getInstance()->api()->setMyName($name, $options);
    }

    public static function getMyName(array $options = []): array
    {
        return static::getInstance()->api()->getMyName($options);
    }

    public static function setMyDescription(string $description = '', array $options = []): bool
    {
        return static::getInstance()->api()->setMyDescription($description, $options);
    }

    public static function getMyDescription(array $options = []): array
    {
        return static::getInstance()->api()->getMyDescription($options);
    }

    public static function setMyShortDescription(string $shortDescription = '', array $options = []): bool
    {
        return static::getInstance()->api()->setMyShortDescription($shortDescription, $options);
    }

    public static function getMyShortDescription(array $options = []): array
    {
        return static::getInstance()->api()->getMyShortDescription($options);
    }

    // -------------------------------------------------------------------------
    // Payments
    // -------------------------------------------------------------------------

    public static function sendInvoice(
        int|string $chatId,
        string $title,
        string $description,
        string $payload,
        string $currency,
        array $prices,
        array $options = [],
    ): Message {
        return static::getInstance()->api()->sendInvoice($chatId, $title, $description, $payload, $currency, $prices, $options);
    }

    public static function answerShippingQuery(string $shippingQueryId, bool $ok, array $options = []): bool
    {
        return static::getInstance()->api()->answerShippingQuery($shippingQueryId, $ok, $options);
    }

    public static function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, array $options = []): bool
    {
        return static::getInstance()->api()->answerPreCheckoutQuery($preCheckoutQueryId, $ok, $options);
    }

    public static function deleteMessages(int|string $chatId, array $messageIds): bool
    {
        return static::getInstance()->api()->deleteMessages($chatId, $messageIds);
    }

    // -------------------------------------------------------------------------
    // Logging helpers — delegates to Log::
    // -------------------------------------------------------------------------

    /** Send $data to the configured admin chat ID via Telegram. Falls back to saveLog() if unavailable. */
    public static function log(mixed $data): void
    {
        \Devflow\TelegramBot\Support\Log::send($data);
    }

    /** Write $data to the daily log file. Pass $_depth=2 if wrapping in another function. */
    public static function saveLog(mixed $data, string $level = 'INFO', int $_depth = 1): void
    {
        \Devflow\TelegramBot\Support\Log::save($data, $level, $_depth + 1);
    }
}
