<?php

namespace Devflow\TelegramBot\Types;

class PollAnswer
{
    public function __construct(
        public readonly string $pollId,
        public readonly array $optionIds,
        public readonly ?User $user = null,
        public readonly ?Chat $voterChat = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            pollId: $data['poll_id'],
            optionIds: $data['option_ids'],
            user: isset($data['user']) ? User::fromArray($data['user']) : null,
            voterChat: isset($data['voter_chat']) ? Chat::fromArray($data['voter_chat']) : null,
        );
    }
}
