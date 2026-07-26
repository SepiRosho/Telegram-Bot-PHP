<?php

namespace Devflow\TelegramBot\Testing;

use Devflow\TelegramBot\Types\Update;

/**
 * Drop-in stand-in for Database\UserRepository, backed by an in-memory array
 * instead of Eloquent — lets step()/temp() flows be tested with no database.
 */
class FakeUserRepository
{
    /** @var array<int, FakeUser> */
    private array $users = [];

    private int $nextId = 1;

    public function findOrCreateByUpdate(Update $update): ?FakeUser
    {
        $from = $update->message?->from
            ?? $update->editedMessage?->from
            ?? $update->callbackQuery?->from
            ?? $update->inlineQuery?->from;

        if ($from === null) {
            return null;
        }

        $chatId = $update->message?->chat->id
            ?? $update->editedMessage?->chat->id
            ?? $update->callbackQuery?->message?->chat->id
            ?? $from->id;

        if (!isset($this->users[$from->id])) {
            $this->users[$from->id] = new FakeUser(
                telegram_id: $from->id,
                chat_id: $chatId,
                first_name: $from->firstName,
                last_name: $from->lastName,
                username: $from->username,
                language_code: $from->languageCode,
                id: $this->nextId++,
            );
        }

        return $this->users[$from->id];
    }

    public function get(int $telegramId): ?FakeUser
    {
        return $this->users[$telegramId] ?? null;
    }

    /** Look up by surrogate id, mirroring an Eloquent `find()`. */
    public function find(int $id): ?FakeUser
    {
        foreach ($this->users as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }

    public function all(): array
    {
        return $this->users;
    }
}
