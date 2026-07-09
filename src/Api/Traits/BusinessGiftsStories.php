<?php

namespace Devflow\TelegramBot\Api\Traits;

use Devflow\TelegramBot\Types\BusinessConnection;

trait BusinessGiftsStories
{
    // -------------------------------------------------------------------------
    // Business accounts
    // -------------------------------------------------------------------------

    public function getBusinessConnection(string $businessConnectionId): BusinessConnection
    {
        return BusinessConnection::fromArray($this->http->post('getBusinessConnection', [
            'business_connection_id' => $businessConnectionId,
        ]));
    }

    public function readBusinessMessage(string $businessConnectionId, int|string $chatId, int $messageId): bool
    {
        return (bool) $this->http->post('readBusinessMessage', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function deleteBusinessMessages(string $businessConnectionId, array $messageIds): bool
    {
        return (bool) $this->http->post('deleteBusinessMessages', [
            'business_connection_id' => $businessConnectionId,
            'message_ids' => $messageIds,
        ]);
    }

    public function setBusinessAccountName(string $businessConnectionId, string $firstName, array $options = []): bool
    {
        return (bool) $this->http->post('setBusinessAccountName', array_merge([
            'business_connection_id' => $businessConnectionId,
            'first_name' => $firstName,
        ], $options));
    }

    public function setBusinessAccountUsername(string $businessConnectionId, array $options = []): bool
    {
        return (bool) $this->http->post('setBusinessAccountUsername', array_merge([
            'business_connection_id' => $businessConnectionId,
        ], $options));
    }

    public function setBusinessAccountBio(string $businessConnectionId, array $options = []): bool
    {
        return (bool) $this->http->post('setBusinessAccountBio', array_merge([
            'business_connection_id' => $businessConnectionId,
        ], $options));
    }

    public function setBusinessAccountProfilePhoto(string $businessConnectionId, mixed $photo, array $options = []): bool
    {
        return (bool) $this->http->post('setBusinessAccountProfilePhoto', array_merge([
            'business_connection_id' => $businessConnectionId,
            'photo' => $photo,
        ], $options));
    }

    public function removeBusinessAccountProfilePhoto(string $businessConnectionId, array $options = []): bool
    {
        return (bool) $this->http->post('removeBusinessAccountProfilePhoto', array_merge([
            'business_connection_id' => $businessConnectionId,
        ], $options));
    }

    public function setBusinessAccountGiftSettings(string $businessConnectionId, bool $showGiftButton, array $acceptedGiftTypes): bool
    {
        return (bool) $this->http->post('setBusinessAccountGiftSettings', [
            'business_connection_id' => $businessConnectionId,
            'show_gift_button' => $showGiftButton,
            'accepted_gift_types' => $acceptedGiftTypes,
        ]);
    }

    public function getBusinessAccountStarBalance(string $businessConnectionId): array
    {
        return $this->http->post('getBusinessAccountStarBalance', [
            'business_connection_id' => $businessConnectionId,
        ]);
    }

    public function transferBusinessAccountStars(string $businessConnectionId, int $starCount): bool
    {
        return (bool) $this->http->post('transferBusinessAccountStars', [
            'business_connection_id' => $businessConnectionId,
            'star_count' => $starCount,
        ]);
    }

    public function getBusinessAccountGifts(string $businessConnectionId, array $options = []): array
    {
        return $this->http->post('getBusinessAccountGifts', array_merge([
            'business_connection_id' => $businessConnectionId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Gifts & Stars
    // -------------------------------------------------------------------------

    public function getAvailableGifts(): array
    {
        return $this->http->post('getAvailableGifts');
    }

    public function sendGift(string $giftId, array $options = []): bool
    {
        return (bool) $this->http->post('sendGift', array_merge([
            'gift_id' => $giftId,
        ], $options));
    }

    public function giftPremiumSubscription(int $userId, int $monthCount, int $starCount, array $options = []): bool
    {
        return (bool) $this->http->post('giftPremiumSubscription', array_merge([
            'user_id' => $userId,
            'month_count' => $monthCount,
            'star_count' => $starCount,
        ], $options));
    }

    public function getUserGifts(int $userId, array $options = []): array
    {
        return $this->http->post('getUserGifts', array_merge([
            'user_id' => $userId,
        ], $options));
    }

    public function getChatGifts(int|string $chatId, array $options = []): array
    {
        return $this->http->post('getChatGifts', array_merge([
            'chat_id' => $chatId,
        ], $options));
    }

    public function convertGiftToStars(string $businessConnectionId, string $ownedGiftId): bool
    {
        return (bool) $this->http->post('convertGiftToStars', [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
        ]);
    }

    public function upgradeGift(string $businessConnectionId, string $ownedGiftId, array $options = []): bool
    {
        return (bool) $this->http->post('upgradeGift', array_merge([
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
        ], $options));
    }

    public function transferGift(string $businessConnectionId, string $ownedGiftId, int $newOwnerChatId, array $options = []): bool
    {
        return (bool) $this->http->post('transferGift', array_merge([
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
            'new_owner_chat_id' => $newOwnerChatId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Verification
    // -------------------------------------------------------------------------

    public function verifyUser(int $userId, array $options = []): bool
    {
        return (bool) $this->http->post('verifyUser', array_merge([
            'user_id' => $userId,
        ], $options));
    }

    public function verifyChat(int|string $chatId, array $options = []): bool
    {
        return (bool) $this->http->post('verifyChat', array_merge([
            'chat_id' => $chatId,
        ], $options));
    }

    public function removeUserVerification(int $userId): bool
    {
        return (bool) $this->http->post('removeUserVerification', [
            'user_id' => $userId,
        ]);
    }

    public function removeChatVerification(int|string $chatId): bool
    {
        return (bool) $this->http->post('removeChatVerification', [
            'chat_id' => $chatId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Stories
    // -------------------------------------------------------------------------

    public function postStory(string $businessConnectionId, array $content, int $activePeriod, array $options = []): array
    {
        return $this->http->post('postStory', array_merge([
            'business_connection_id' => $businessConnectionId,
            'content' => $content,
            'active_period' => $activePeriod,
        ], $options));
    }

    public function repostStory(string $businessConnectionId, int|string $fromChatId, int $fromStoryId, int $activePeriod, array $options = []): array
    {
        return $this->http->post('repostStory', array_merge([
            'business_connection_id' => $businessConnectionId,
            'from_chat_id' => $fromChatId,
            'from_story_id' => $fromStoryId,
            'active_period' => $activePeriod,
        ], $options));
    }

    public function editStory(string $businessConnectionId, int $storyId, array $content, array $options = []): array
    {
        return $this->http->post('editStory', array_merge([
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
            'content' => $content,
        ], $options));
    }

    public function deleteStory(string $businessConnectionId, int $storyId): bool
    {
        return (bool) $this->http->post('deleteStory', [
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
        ]);
    }
}
