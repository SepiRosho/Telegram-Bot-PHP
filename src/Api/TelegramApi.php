<?php

namespace Devflow\TelegramBot\Api;

use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\User;

class TelegramApi
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function getMe(): User
    {
        return User::fromArray($this->http->post('getMe'));
    }

    // -------------------------------------------------------------------------
    // Sending messages
    // -------------------------------------------------------------------------

    public function sendMessage(int|string $chatId, string $text, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options)));
    }

    public function sendPhoto(int|string $chatId, string $photo, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
        ], $options)));
    }

    public function sendDocument(int|string $chatId, string $document, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendDocument', array_merge([
            'chat_id' => $chatId,
            'document' => $document,
        ], $options)));
    }

    public function sendAudio(int|string $chatId, string $audio, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendAudio', array_merge([
            'chat_id' => $chatId,
            'audio' => $audio,
        ], $options)));
    }

    public function sendVideo(int|string $chatId, string $video, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
        ], $options)));
    }

    public function sendAnimation(int|string $chatId, string $animation, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendAnimation', array_merge([
            'chat_id' => $chatId,
            'animation' => $animation,
        ], $options)));
    }

    public function sendVoice(int|string $chatId, string $voice, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendVoice', array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
        ], $options)));
    }

    public function sendSticker(int|string $chatId, string $sticker, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendSticker', array_merge([
            'chat_id' => $chatId,
            'sticker' => $sticker,
        ], $options)));
    }

    public function sendLocation(int|string $chatId, float $latitude, float $longitude, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendLocation', array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], $options)));
    }

    public function sendContact(int|string $chatId, string $phoneNumber, string $firstName, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendContact', array_merge([
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ], $options)));
    }

    public function sendPoll(int|string $chatId, string $question, array $pollOptions, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendPoll', array_merge([
            'chat_id' => $chatId,
            'question' => $question,
            'options' => $pollOptions,
        ], $options)));
    }

    public function sendDice(int|string $chatId, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendDice', array_merge([
            'chat_id' => $chatId,
        ], $options)));
    }

    public function sendChatAction(int|string $chatId, string $action): bool
    {
        return (bool) $this->http->post('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    // -------------------------------------------------------------------------
    // Editing messages
    // -------------------------------------------------------------------------

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $options = []): Message
    {
        return Message::fromArray($this->http->post('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $options)));
    }

    public function editMessageCaption(int|string $chatId, int $messageId, string $caption, array $options = []): Message
    {
        return Message::fromArray($this->http->post('editMessageCaption', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
        ], $options)));
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): Message
    {
        return Message::fromArray($this->http->post('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]));
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        return (bool) $this->http->post('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function forwardMessage(int|string $chatId, int|string $fromChatId, int $messageId, array $options = []): Message
    {
        return Message::fromArray($this->http->post('forwardMessage', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ], $options)));
    }

    public function copyMessage(int|string $chatId, int|string $fromChatId, int $messageId, array $options = []): array
    {
        return $this->http->post('copyMessage', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Callback queries
    // -------------------------------------------------------------------------

    public function answerCallbackQuery(string $callbackQueryId, array $options = []): bool
    {
        return (bool) $this->http->post('answerCallbackQuery', array_merge([
            'callback_query_id' => $callbackQueryId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Chat administration
    // -------------------------------------------------------------------------

    public function pinChatMessage(int|string $chatId, int $messageId, bool $disableNotification = false): bool
    {
        return (bool) $this->http->post('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): bool
    {
        return (bool) $this->http->post('unpinChatMessage', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]));
    }

    public function banChatMember(int|string $chatId, int $userId, array $options = []): bool
    {
        return (bool) $this->http->post('banChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ], $options));
    }

    public function unbanChatMember(int|string $chatId, int $userId, bool $onlyIfBanned = true): bool
    {
        return (bool) $this->http->post('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned,
        ]);
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    public function setWebhook(string $url, array $options = []): bool
    {
        return (bool) $this->http->post('setWebhook', array_merge([
            'url' => $url,
        ], $options));
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return (bool) $this->http->post('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    public function getWebhookInfo(): array
    {
        return $this->http->post('getWebhookInfo');
    }

    // -------------------------------------------------------------------------
    // Venue & media group
    // -------------------------------------------------------------------------

    public function sendVenue(int|string $chatId, float $latitude, float $longitude, string $title, string $address, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendVenue', array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ], $options)));
    }

    public function sendMediaGroup(int|string $chatId, array $media, array $options = []): array
    {
        return $this->http->post('sendMediaGroup', array_merge([
            'chat_id' => $chatId,
            'media' => $media,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Inline queries
    // -------------------------------------------------------------------------

    public function answerInlineQuery(string $inlineQueryId, array $results, array $options = []): bool
    {
        return (bool) $this->http->post('answerInlineQuery', array_merge([
            'inline_query_id' => $inlineQueryId,
            'results' => $results,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Chat info
    // -------------------------------------------------------------------------

    public function getChat(int|string $chatId): array
    {
        return $this->http->post('getChat', ['chat_id' => $chatId]);
    }

    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->http->post('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function getChatMemberCount(int|string $chatId): int
    {
        return (int) $this->http->post('getChatMemberCount', ['chat_id' => $chatId]);
    }

    public function leaveChat(int|string $chatId): bool
    {
        return (bool) $this->http->post('leaveChat', ['chat_id' => $chatId]);
    }

    public function exportChatInviteLink(int|string $chatId): string
    {
        return (string) $this->http->post('exportChatInviteLink', ['chat_id' => $chatId]);
    }

    public function createChatInviteLink(int|string $chatId, array $options = []): array
    {
        return $this->http->post('createChatInviteLink', array_merge([
            'chat_id' => $chatId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Chat member permissions
    // -------------------------------------------------------------------------

    public function restrictChatMember(int|string $chatId, int $userId, array $permissions, array $options = []): bool
    {
        return (bool) $this->http->post('restrictChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => $permissions,
        ], $options));
    }

    public function promoteChatMember(int|string $chatId, int $userId, array $options = []): bool
    {
        return (bool) $this->http->post('promoteChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ], $options));
    }

    public function setChatAdministratorCustomTitle(int|string $chatId, int $userId, string $customTitle): bool
    {
        return (bool) $this->http->post('setChatAdministratorCustomTitle', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle,
        ]);
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    public function getFile(string $fileId): array
    {
        return $this->http->post('getFile', ['file_id' => $fileId]);
    }

    public function getUserProfilePhotos(int $userId, array $options = []): array
    {
        return $this->http->post('getUserProfilePhotos', array_merge([
            'user_id' => $userId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Bot commands
    // -------------------------------------------------------------------------

    public function setMyCommands(array $commands, array $options = []): bool
    {
        return (bool) $this->http->post('setMyCommands', array_merge([
            'commands' => $commands,
        ], $options));
    }

    public function getMyCommands(array $options = []): array
    {
        return $this->http->post('getMyCommands', $options);
    }

    public function deleteMyCommands(array $options = []): bool
    {
        return (bool) $this->http->post('deleteMyCommands', $options);
    }
}
