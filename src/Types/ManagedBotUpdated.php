<?php

namespace Devflow\TelegramBot\Types;

class ManagedBotUpdated
{
    public function __construct(
        public readonly User $user,
        public readonly User $bot,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user: User::fromArray($data['user']),
            bot: User::fromArray($data['bot']),
        );
    }
}
