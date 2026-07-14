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
        public readonly ?string $caption = null,
        public readonly ?array $photo = null,
        public readonly ?array $document = null,
        public readonly ?array $audio = null,
        public readonly ?array $video = null,
        public readonly ?array $voice = null,
        public readonly ?array $videoNote = null,
        public readonly ?array $sticker = null,
        public readonly ?array $animation = null,
        public readonly ?array $contact = null,
        public readonly ?array $location = null,
        public readonly ?array $venue = null,
        public readonly ?array $dice = null,
        public readonly ?array $forwardOrigin = null,
        public readonly ?int $forwardDate = null,
        public readonly ?Message $replyToMessage = null,
        public readonly ?array $entities = null,
        public readonly ?array $captionEntities = null,
        public readonly ?array $replyMarkup = null,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            from: isset($data['from']) ? User::fromArray($data['from']) : null,
            chat: Chat::fromArray($data['chat']),
            date: $data['date'],
            text: $data['text'] ?? null,
            caption: $data['caption'] ?? null,
            photo: $data['photo'] ?? null,
            document: $data['document'] ?? null,
            audio: $data['audio'] ?? null,
            video: $data['video'] ?? null,
            voice: $data['voice'] ?? null,
            videoNote: $data['video_note'] ?? null,
            sticker: $data['sticker'] ?? null,
            animation: $data['animation'] ?? null,
            contact: $data['contact'] ?? null,
            location: $data['location'] ?? null,
            venue: $data['venue'] ?? null,
            dice: $data['dice'] ?? null,
            forwardOrigin: $data['forward_origin'] ?? null,
            forwardDate: $data['forward_date'] ?? null,
            // Telegram never nests reply_to_message more than one level deep.
            replyToMessage: isset($data['reply_to_message']) ? self::fromArray($data['reply_to_message']) : null,
            entities: $data['entities'] ?? null,
            captionEntities: $data['caption_entities'] ?? null,
            replyMarkup: $data['reply_markup'] ?? null,
            raw: $data,
        );
    }

    /**
     * Escape hatch: the full raw update array for fields not otherwise mapped.
     */
    public function raw(): array
    {
        return $this->raw;
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
