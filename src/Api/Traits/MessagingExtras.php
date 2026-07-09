<?php

namespace Devflow\TelegramBot\Api\Traits;

use Devflow\TelegramBot\Types\Message;

trait MessagingExtras
{
    // -------------------------------------------------------------------------
    // Bulk message operations
    // -------------------------------------------------------------------------

    public function copyMessages(int|string $chatId, int|string $fromChatId, array $messageIds, array $options = []): array
    {
        return $this->http->post('copyMessages', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => $messageIds,
        ], $options));
    }

    public function forwardMessages(int|string $chatId, int|string $fromChatId, array $messageIds, array $options = []): array
    {
        return $this->http->post('forwardMessages', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => $messageIds,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Live location
    // -------------------------------------------------------------------------

    public function editMessageLiveLocation(float $latitude, float $longitude, array $options = []): Message
    {
        return Message::fromArray($this->http->post('editMessageLiveLocation', array_merge([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], $options)));
    }

    public function stopMessageLiveLocation(array $options = []): Message
    {
        return Message::fromArray($this->http->post('stopMessageLiveLocation', $options));
    }

    // -------------------------------------------------------------------------
    // Message reactions
    // -------------------------------------------------------------------------

    public function setMessageReaction(int|string $chatId, int $messageId, array $options = []): bool
    {
        return (bool) $this->http->post('setMessageReaction', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options));
    }

    public function deleteMessageReaction(int|string $chatId, int $messageId, array $options = []): bool
    {
        return (bool) $this->http->post('deleteMessageReaction', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options));
    }

    public function deleteAllMessageReactions(int|string $chatId, array $options = []): bool
    {
        return (bool) $this->http->post('deleteAllMessageReactions', array_merge([
            'chat_id' => $chatId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Checklists
    // -------------------------------------------------------------------------

    public function sendChecklist(string $businessConnectionId, int|string $chatId, array $checklist, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendChecklist', array_merge([
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'checklist' => $checklist,
        ], $options)));
    }

    public function editMessageChecklist(string $businessConnectionId, int|string $chatId, int $messageId, array $checklist, array $options = []): Message
    {
        return Message::fromArray($this->http->post('editMessageChecklist', array_merge([
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'checklist' => $checklist,
        ], $options)));
    }

    // -------------------------------------------------------------------------
    // Paid media & live photo
    // -------------------------------------------------------------------------

    public function sendPaidMedia(int|string $chatId, int $starCount, array $media, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendPaidMedia', array_merge([
            'chat_id' => $chatId,
            'star_count' => $starCount,
            'media' => $media,
        ], $options)));
    }

    public function sendLivePhoto(int|string $chatId, mixed $livePhoto, mixed $photo, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendLivePhoto', array_merge([
            'chat_id' => $chatId,
            'live_photo' => $livePhoto,
            'photo' => $photo,
        ], $options)));
    }

    // -------------------------------------------------------------------------
    // Message drafts & rich messages
    // -------------------------------------------------------------------------

    public function sendMessageDraft(int|string $chatId, int $draftId, array $options = []): bool
    {
        return (bool) $this->http->post('sendMessageDraft', array_merge([
            'chat_id' => $chatId,
            'draft_id' => $draftId,
        ], $options));
    }

    public function sendRichMessage(int|string $chatId, array $richMessage, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendRichMessage', array_merge([
            'chat_id' => $chatId,
            'rich_message' => $richMessage,
        ], $options)));
    }

    public function sendRichMessageDraft(int|string $chatId, int $draftId, array $richMessage, array $options = []): bool
    {
        return (bool) $this->http->post('sendRichMessageDraft', array_merge([
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'rich_message' => $richMessage,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Suggested posts
    // -------------------------------------------------------------------------

    public function approveSuggestedPost(int|string $chatId, int $messageId, array $options = []): bool
    {
        return (bool) $this->http->post('approveSuggestedPost', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options));
    }

    public function declineSuggestedPost(int|string $chatId, int $messageId, array $options = []): bool
    {
        return (bool) $this->http->post('declineSuggestedPost', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Bot profile photo
    // -------------------------------------------------------------------------

    public function setMyProfilePhoto(mixed $photo): bool
    {
        return (bool) $this->http->post('setMyProfilePhoto', [
            'photo' => $photo,
        ]);
    }

    public function removeMyProfilePhoto(): bool
    {
        return (bool) $this->http->post('removeMyProfilePhoto');
    }

    // -------------------------------------------------------------------------
    // Default administrator rights
    // -------------------------------------------------------------------------

    public function setMyDefaultAdministratorRights(array $options = []): bool
    {
        return (bool) $this->http->post('setMyDefaultAdministratorRights', $options);
    }

    public function getMyDefaultAdministratorRights(array $options = []): array
    {
        return $this->http->post('getMyDefaultAdministratorRights', $options);
    }
}
