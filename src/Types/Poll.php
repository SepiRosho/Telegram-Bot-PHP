<?php

namespace Devflow\TelegramBot\Types;

class Poll
{
    public function __construct(
        public readonly string $id,
        public readonly string $question,
        public readonly array $options,
        public readonly int $totalVoterCount,
        public readonly bool $isClosed,
        public readonly bool $isAnonymous,
        public readonly string $type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            question: $data['question'],
            options: $data['options'],
            totalVoterCount: $data['total_voter_count'],
            isClosed: $data['is_closed'],
            isAnonymous: $data['is_anonymous'],
            type: $data['type'],
        );
    }
}
