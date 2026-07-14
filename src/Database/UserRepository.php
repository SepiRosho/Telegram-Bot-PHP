<?php

namespace Devflow\TelegramBot\Database;

use Devflow\TelegramBot\Database\Models\TelegramUser;
use Devflow\TelegramBot\Types\Update;

class UserRepository
{
    public function __construct(private array $config = []) {}

    public function findOrCreateByUpdate(Update $update): ?TelegramUser
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

        $modelClass = $this->config['user_model'] ?? TelegramUser::class;

        $user = $modelClass::firstOrCreate(
            ['telegram_id' => $from->id],
            [
                'chat_id'       => $chatId,
                'first_name'    => $from->firstName,
                'last_name'     => $from->lastName,
                'username'      => $from->username,
                'language_code' => $from->languageCode,
            ]
        );

        $user->chat_id    = $chatId;
        $user->first_name = $from->firstName;
        $user->last_name  = $from->lastName;
        $user->username   = $from->username;
        $user->is_active  = true;

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
