<?php

namespace Devflow\TelegramBot\Testing;

/**
 * In-memory stand-in for Database\Models\TelegramUser, used by
 * FakeUserRepository so step()/temp()/setLocale() flows in Context can be
 * exercised with no real database.
 */
class FakeUser
{
    public ?string $step = null;
    public mixed $temp_data = null;
    public ?string $language = null;
    public bool $is_banned = false;
    public bool $is_active = true;
    public string $role = 'user';

    public function __construct(
        public int $telegram_id,
        public int $chat_id,
        public string $first_name,
        public ?string $last_name = null,
        public ?string $username = null,
        public ?string $language_code = null,
    ) {}

    public function save(): void
    {
        // No-op — properties are already mutated in place.
    }
}
