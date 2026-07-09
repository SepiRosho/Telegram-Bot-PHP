<?php

namespace Devflow\TelegramBot\Api\Traits;

trait ForumAndChatAdmin
{
    // -------------------------------------------------------------------------
    // Sender chat bans
    // -------------------------------------------------------------------------

    public function banChatSenderChat(int|string $chatId, int $senderChatId): bool
    {
        return (bool) $this->http->post('banChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId,
        ]);
    }

    public function unbanChatSenderChat(int|string $chatId, int $senderChatId): bool
    {
        return (bool) $this->http->post('unbanChatSenderChat', [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Invite links
    // -------------------------------------------------------------------------

    public function createChatSubscriptionInviteLink(int|string $chatId, int $subscriptionPeriod, int $subscriptionPrice, array $options = []): array
    {
        return $this->http->post('createChatSubscriptionInviteLink', array_merge([
            'chat_id' => $chatId,
            'subscription_period' => $subscriptionPeriod,
            'subscription_price' => $subscriptionPrice,
        ], $options));
    }

    public function editChatSubscriptionInviteLink(int|string $chatId, string $inviteLink, array $options = []): array
    {
        return $this->http->post('editChatSubscriptionInviteLink', array_merge([
            'chat_id' => $chatId,
            'invite_link' => $inviteLink,
        ], $options));
    }

    public function editChatInviteLink(int|string $chatId, string $inviteLink, array $options = []): array
    {
        return $this->http->post('editChatInviteLink', array_merge([
            'chat_id' => $chatId,
            'invite_link' => $inviteLink,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Sticker sets
    // -------------------------------------------------------------------------

    public function setChatStickerSet(int|string $chatId, string $stickerSetName): bool
    {
        return (bool) $this->http->post('setChatStickerSet', [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName,
        ]);
    }

    public function deleteChatStickerSet(int|string $chatId): bool
    {
        return (bool) $this->http->post('deleteChatStickerSet', ['chat_id' => $chatId]);
    }

    // -------------------------------------------------------------------------
    // Forum topics
    // -------------------------------------------------------------------------

    public function getForumTopicIconStickers(): array
    {
        return $this->http->post('getForumTopicIconStickers');
    }

    public function createForumTopic(int|string $chatId, string $name, array $options = []): array
    {
        return $this->http->post('createForumTopic', array_merge([
            'chat_id' => $chatId,
            'name' => $name,
        ], $options));
    }

    public function editForumTopic(int|string $chatId, int $messageThreadId, array $options = []): bool
    {
        return (bool) $this->http->post('editForumTopic', array_merge([
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ], $options));
    }

    public function closeForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return (bool) $this->http->post('closeForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    public function reopenForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return (bool) $this->http->post('reopenForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    public function deleteForumTopic(int|string $chatId, int $messageThreadId): bool
    {
        return (bool) $this->http->post('deleteForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    public function unpinAllForumTopicMessages(int|string $chatId, int $messageThreadId): bool
    {
        return (bool) $this->http->post('unpinAllForumTopicMessages', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    // -------------------------------------------------------------------------
    // General forum topic
    // -------------------------------------------------------------------------

    public function editGeneralForumTopic(int|string $chatId, string $name): bool
    {
        return (bool) $this->http->post('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
        ]);
    }

    public function closeGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->http->post('closeGeneralForumTopic', ['chat_id' => $chatId]);
    }

    public function reopenGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->http->post('reopenGeneralForumTopic', ['chat_id' => $chatId]);
    }

    public function hideGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->http->post('hideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    public function unhideGeneralForumTopic(int|string $chatId): bool
    {
        return (bool) $this->http->post('unhideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    public function unpinAllGeneralForumTopicMessages(int|string $chatId): bool
    {
        return (bool) $this->http->post('unpinAllGeneralForumTopicMessages', ['chat_id' => $chatId]);
    }

    // -------------------------------------------------------------------------
    // Chat member tag & boosts
    // -------------------------------------------------------------------------

    public function setChatMemberTag(int|string $chatId, int $userId, array $options = []): bool
    {
        return (bool) $this->http->post('setChatMemberTag', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ], $options));
    }

    public function getUserChatBoosts(int|string $chatId, int $userId): array
    {
        return $this->http->post('getUserChatBoosts', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Chat join request queries
    // -------------------------------------------------------------------------

    public function answerChatJoinRequestQuery(string $chatJoinRequestQueryId, string $result, array $options = []): bool
    {
        return (bool) $this->http->post('answerChatJoinRequestQuery', array_merge([
            'chat_join_request_query_id' => $chatJoinRequestQueryId,
            'result' => $result,
        ], $options));
    }

    public function sendChatJoinRequestWebApp(string $chatJoinRequestQueryId, string $webAppUrl): bool
    {
        return (bool) $this->http->post('sendChatJoinRequestWebApp', [
            'chat_join_request_query_id' => $chatJoinRequestQueryId,
            'web_app_url' => $webAppUrl,
        ]);
    }
}
