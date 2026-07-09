<?php

namespace Devflow\TelegramBot\Types;

class Update
{
    public function __construct(
        public readonly int $updateId,
        public readonly ?Message $message = null,
        public readonly ?Message $editedMessage = null,
        public readonly ?Message $channelPost = null,
        public readonly ?Message $editedChannelPost = null,
        public readonly ?CallbackQuery $callbackQuery = null,
        public readonly ?InlineQuery $inlineQuery = null,
        public readonly ?ChosenInlineResult $chosenInlineResult = null,
        public readonly ?Poll $poll = null,
        public readonly ?PollAnswer $pollAnswer = null,
        public readonly ?ChatMemberUpdated $myChatMember = null,
        public readonly ?ChatMemberUpdated $chatMember = null,
        public readonly ?ChatJoinRequest $chatJoinRequest = null,
        public readonly ?ShippingQuery $shippingQuery = null,
        public readonly ?PreCheckoutQuery $preCheckoutQuery = null,
        public readonly ?BusinessConnection $businessConnection = null,
        public readonly ?Message $businessMessage = null,
        public readonly ?Message $editedBusinessMessage = null,
        public readonly ?BusinessMessagesDeleted $deletedBusinessMessages = null,
        public readonly ?Message $guestMessage = null,
        public readonly ?MessageReactionUpdated $messageReaction = null,
        public readonly ?MessageReactionCountUpdated $messageReactionCount = null,
        public readonly ?PaidMediaPurchased $purchasedPaidMedia = null,
        public readonly ?ChatBoostUpdated $chatBoost = null,
        public readonly ?ChatBoostRemoved $removedChatBoost = null,
        public readonly ?ManagedBotUpdated $managedBot = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            updateId: $data['update_id'],
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            editedMessage: isset($data['edited_message']) ? Message::fromArray($data['edited_message']) : null,
            channelPost: isset($data['channel_post']) ? Message::fromArray($data['channel_post']) : null,
            editedChannelPost: isset($data['edited_channel_post']) ? Message::fromArray($data['edited_channel_post']) : null,
            callbackQuery: isset($data['callback_query']) ? CallbackQuery::fromArray($data['callback_query']) : null,
            inlineQuery: isset($data['inline_query']) ? InlineQuery::fromArray($data['inline_query']) : null,
            chosenInlineResult: isset($data['chosen_inline_result']) ? ChosenInlineResult::fromArray($data['chosen_inline_result']) : null,
            poll: isset($data['poll']) ? Poll::fromArray($data['poll']) : null,
            pollAnswer: isset($data['poll_answer']) ? PollAnswer::fromArray($data['poll_answer']) : null,
            myChatMember: isset($data['my_chat_member']) ? ChatMemberUpdated::fromArray($data['my_chat_member']) : null,
            chatMember: isset($data['chat_member']) ? ChatMemberUpdated::fromArray($data['chat_member']) : null,
            chatJoinRequest: isset($data['chat_join_request']) ? ChatJoinRequest::fromArray($data['chat_join_request']) : null,
            shippingQuery: isset($data['shipping_query']) ? ShippingQuery::fromArray($data['shipping_query']) : null,
            preCheckoutQuery: isset($data['pre_checkout_query']) ? PreCheckoutQuery::fromArray($data['pre_checkout_query']) : null,
            businessConnection: isset($data['business_connection']) ? BusinessConnection::fromArray($data['business_connection']) : null,
            businessMessage: isset($data['business_message']) ? Message::fromArray($data['business_message']) : null,
            editedBusinessMessage: isset($data['edited_business_message']) ? Message::fromArray($data['edited_business_message']) : null,
            deletedBusinessMessages: isset($data['deleted_business_messages']) ? BusinessMessagesDeleted::fromArray($data['deleted_business_messages']) : null,
            guestMessage: isset($data['guest_message']) ? Message::fromArray($data['guest_message']) : null,
            messageReaction: isset($data['message_reaction']) ? MessageReactionUpdated::fromArray($data['message_reaction']) : null,
            messageReactionCount: isset($data['message_reaction_count']) ? MessageReactionCountUpdated::fromArray($data['message_reaction_count']) : null,
            purchasedPaidMedia: isset($data['purchased_paid_media']) ? PaidMediaPurchased::fromArray($data['purchased_paid_media']) : null,
            chatBoost: isset($data['chat_boost']) ? ChatBoostUpdated::fromArray($data['chat_boost']) : null,
            removedChatBoost: isset($data['removed_chat_boost']) ? ChatBoostRemoved::fromArray($data['removed_chat_boost']) : null,
            managedBot: isset($data['managed_bot']) ? ManagedBotUpdated::fromArray($data['managed_bot']) : null,
        );
    }

    public function type(): string
    {
        return match (true) {
            $this->callbackQuery !== null     => 'callback_query',
            $this->inlineQuery !== null       => 'inline_query',
            $this->chosenInlineResult !== null => 'chosen_inline_result',
            $this->poll !== null              => 'poll',
            $this->pollAnswer !== null        => 'poll_answer',
            $this->myChatMember !== null      => 'my_chat_member',
            $this->chatMember !== null        => 'chat_member',
            $this->chatJoinRequest !== null   => 'chat_join_request',
            $this->shippingQuery !== null     => 'shipping_query',
            $this->preCheckoutQuery !== null  => 'pre_checkout_query',
            $this->businessConnection !== null => 'business_connection',
            $this->businessMessage !== null   => 'business_message',
            $this->editedBusinessMessage !== null => 'edited_business_message',
            $this->deletedBusinessMessages !== null => 'deleted_business_messages',
            $this->guestMessage !== null      => 'guest_message',
            $this->messageReaction !== null   => 'message_reaction',
            $this->messageReactionCount !== null => 'message_reaction_count',
            $this->purchasedPaidMedia !== null => 'purchased_paid_media',
            $this->chatBoost !== null         => 'chat_boost',
            $this->removedChatBoost !== null  => 'removed_chat_boost',
            $this->managedBot !== null        => 'managed_bot',
            $this->editedMessage !== null     => 'edited_message',
            $this->editedChannelPost !== null => 'edited_channel_post',
            $this->channelPost !== null       => 'channel_post',
            $this->message !== null           => 'message',
            default                           => 'unknown',
        };
    }
}
