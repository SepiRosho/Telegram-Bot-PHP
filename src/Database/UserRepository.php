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

        // 'language_code' config: 'auto' (default) records whatever Telegram
        // reports. Any other value (e.g. 'fa') locks every user to that
        // language instead — for bots that don't offer multi-language
        // support and don't want per-user locale drift.
        $forcedLocale = $this->config['language_code'] ?? 'auto';
        $forcedLocale = ($forcedLocale !== null && $forcedLocale !== '' && $forcedLocale !== 'auto')
            ? $forcedLocale
            : null;
        $languageCode = $forcedLocale ?? $from->languageCode;

        $attributes = [
            'chat_id'       => $chatId,
            'first_name'    => $from->firstName,
            'last_name'     => $from->lastName,
            'username'      => $from->username,
            'language_code' => $languageCode,
        ];

        if ($forcedLocale !== null) {
            $attributes['language'] = $forcedLocale;
        }

        if (isset($this->config['user_defaults']) && is_callable($this->config['user_defaults'])) {
            $attributes = array_merge($attributes, ($this->config['user_defaults'])($update));
        }

        $user = $modelClass::firstOrCreate(['telegram_id' => $from->id], $attributes);

        $user->chat_id       = $chatId;
        $user->first_name    = $from->firstName;
        $user->last_name     = $from->lastName;
        $user->username      = $from->username;
        $user->language_code = $languageCode;
        $user->is_active     = true;

        if ($forcedLocale !== null) {
            $user->language = $forcedLocale;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
