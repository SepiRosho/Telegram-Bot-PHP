<?php

namespace Devflow\TelegramBot\Types;

class Message
{
    public function __construct(
        public readonly int $messageId,
        public readonly ?User $from,
        public readonly Chat $chat,
        public readonly int $date,
        public readonly ?string $text = null,
        public readonly ?array $photo = null,
        public readonly ?array $document = null,
        public readonly ?array $audio = null,
        public readonly ?array $video = null,
        public readonly ?array $entities = null,
        public readonly ?array $replyMarkup = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            from: isset($data['from']) ? User::fromArray($data['from']) : null,
            chat: Chat::fromArray($data['chat']),
            date: $data['date'],
            text: $data['text'] ?? null,
            photo: $data['photo'] ?? null,
            document: $data['document'] ?? null,
            audio: $data['audio'] ?? null,
            video: $data['video'] ?? null,
            entities: $data['entities'] ?? null,
            replyMarkup: $data['reply_markup'] ?? null,
        );
    }

    public function isCommand(): bool
    {
        if ($this->entities === null) {
            return false;
        }
        foreach ($this->entities as $entity) {
            if ($entity['type'] === 'bot_command' && $entity['offset'] === 0) {
                return true;
            }
        }
        return false;
    }

    public function command(): ?string
    {
        if (!$this->isCommand() || $this->text === null) {
            return null;
        }
        $parts = explode(' ', $this->text);
        $cmd = ltrim($parts[0], '/');
        // Strip @BotName suffix if present
        return explode('@', $cmd)[0];
    }

    public function commandArgs(): array
    {
        if (!$this->isCommand() || $this->text === null) {
            return [];
        }
        $parts = explode(' ', trim($this->text));
        return array_slice($parts, 1);
    }
}
