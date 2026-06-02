<?php

namespace Devflow\TelegramBot\Types;

class Update
{
    public function __construct(
        public readonly int $updateId,
        public readonly ?Message $message = null,
        public readonly ?Message $editedMessage = null,
        public readonly ?Message $channelPost = null,
        public readonly ?CallbackQuery $callbackQuery = null,
        public readonly ?InlineQuery $inlineQuery = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            updateId: $data['update_id'],
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            editedMessage: isset($data['edited_message']) ? Message::fromArray($data['edited_message']) : null,
            channelPost: isset($data['channel_post']) ? Message::fromArray($data['channel_post']) : null,
            callbackQuery: isset($data['callback_query']) ? CallbackQuery::fromArray($data['callback_query']) : null,
            inlineQuery: isset($data['inline_query']) ? InlineQuery::fromArray($data['inline_query']) : null,
        );
    }

    public function type(): string
    {
        return match(true) {
            $this->callbackQuery !== null => 'callback_query',
            $this->inlineQuery !== null => 'inline_query',
            $this->editedMessage !== null => 'edited_message',
            $this->channelPost !== null => 'channel_post',
            $this->message !== null => 'message',
            default => 'unknown',
        };
    }
}
