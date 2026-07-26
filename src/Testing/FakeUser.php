<?php

namespace Devflow\TelegramBot\Testing;

/**
 * In-memory stand-in for Database\Models\TelegramUser, used by
 * FakeUserRepository so step()/temp()/setLocale() flows in Context — and
 * real handler code calling $user->update([...]) — can be exercised with no
 * real database. Backed by an attribute bag (like Eloquent itself) rather
 * than fixed typed properties, so any column your own migrations add is
 * readable/writable here too.
 */
class FakeUser
{
    private array $attributes;

    public function __construct(
        int $telegram_id,
        int $chat_id,
        string $first_name,
        ?string $last_name = null,
        ?string $username = null,
        ?string $language_code = null,
        int $id = 0,
    ) {
        $this->attributes = [
            // Surrogate key, assigned by FakeUserRepository. Real bots put
            // their own tables behind foreign keys to telegram_users.id, so
            // without it no fake user can be related to anything.
            'id'            => $id,
            'telegram_id'   => $telegram_id,
            'chat_id'       => $chat_id,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'username'      => $username,
            'language_code' => $language_code,
            'language'      => null,
            'step'          => null,
            'temp_data'     => null,
            'is_banned'     => false,
            'is_active'     => true,
            'role'          => 'user',
        ];
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /** Mass-assign and "save" (no-op — mutations are already in place). */
    public function update(array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return true;
    }

    public function save(): void
    {
        // No-op — properties are already mutated in place.
    }

    public function isAdmin(): bool
    {
        return in_array($this->attributes['role'] ?? 'user', ['admin', 'superadmin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return ($this->attributes['role'] ?? null) === 'superadmin';
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
