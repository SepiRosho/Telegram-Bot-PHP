<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Types\CallbackQuery;
use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\Update;
use Devflow\TelegramBot\Types\User;

class Context
{
    private ?object $dbUser = null;
    private bool $dbUserLoaded = false;

    public function __construct(
        private readonly Update $update,
        private readonly TelegramApi $api,
        private readonly array $config = [],
    ) {}

    // -------------------------------------------------------------------------
    // Update accessors
    // -------------------------------------------------------------------------

    public function update(): Update
    {
        return $this->update;
    }

    public function api(): TelegramApi
    {
        return $this->api;
    }

    public function message(): ?Message
    {
        return $this->update->message ?? $this->update->editedMessage ?? $this->update->channelPost;
    }

    public function callbackQuery(): ?CallbackQuery
    {
        return $this->update->callbackQuery;
    }

    public function chatId(): int
    {
        if ($this->update->callbackQuery?->message !== null) {
            return $this->update->callbackQuery->message->chat->id;
        }
        return $this->message()?->chat->id ?? 0;
    }

    public function userId(): int
    {
        return $this->update->callbackQuery?->from->id
            ?? $this->message()?->from?->id
            ?? $this->update->inlineQuery?->from->id
            ?? 0;
    }

    public function from(): ?User
    {
        return $this->update->callbackQuery?->from
            ?? $this->message()?->from
            ?? $this->update->inlineQuery?->from;
    }

    public function text(): ?string
    {
        return $this->message()?->text;
    }

    public function callbackData(): ?string
    {
        return $this->update->callbackQuery?->data;
    }

    // -------------------------------------------------------------------------
    // Shorthand API calls
    // -------------------------------------------------------------------------

    public function reply(string $text, array $options = []): Message
    {
        return $this->api->sendMessage($this->chatId(), $text, $options);
    }

    public function replyWithPhoto(string $photo, array $options = []): Message
    {
        return $this->api->sendPhoto($this->chatId(), $photo, $options);
    }

    public function replyWithDocument(string $document, array $options = []): Message
    {
        return $this->api->sendDocument($this->chatId(), $document, $options);
    }

    public function replyWithVideo(string $video, array $options = []): Message
    {
        return $this->api->sendVideo($this->chatId(), $video, $options);
    }

    public function replyWithAudio(string $audio, array $options = []): Message
    {
        return $this->api->sendAudio($this->chatId(), $audio, $options);
    }

    public function replyWithVoice(string $voice, array $options = []): Message
    {
        return $this->api->sendVoice($this->chatId(), $voice, $options);
    }

    public function replyWithSticker(string $sticker, array $options = []): Message
    {
        return $this->api->sendSticker($this->chatId(), $sticker, $options);
    }

    public function replyWithLocation(float $latitude, float $longitude, array $options = []): Message
    {
        return $this->api->sendLocation($this->chatId(), $latitude, $longitude, $options);
    }

    public function typing(): bool
    {
        return $this->api->sendChatAction($this->chatId(), 'typing');
    }

    public function editReply(string $text, array $options = []): Message
    {
        $messageId = $this->update->callbackQuery?->message?->messageId;
        if ($messageId === null) {
            return $this->reply($text, $options);
        }
        return $this->api->editMessageText($this->chatId(), $messageId, $text, $options);
    }

    public function deleteCurrentMessage(): bool
    {
        $messageId = $this->update->callbackQuery?->message?->messageId
            ?? $this->message()?->messageId;
        if ($messageId === null) {
            return false;
        }
        return $this->api->deleteMessage($this->chatId(), $messageId);
    }

    public function answerCallback(string $text = '', bool $showAlert = false): bool
    {
        $id = $this->update->callbackQuery?->id;
        if ($id === null) {
            return false;
        }

        $params = array_filter([
            'text' => $text !== '' ? $text : null,
            'show_alert' => $showAlert ?: null,
        ]);

        return $this->api->answerCallbackQuery($id, $params);
    }

    public function sendChatAction(string $action): bool
    {
        return $this->api->sendChatAction($this->chatId(), $action);
    }

    // -------------------------------------------------------------------------
    // Flow state — wired to DB in Phase 3 via UserRepository
    // -------------------------------------------------------------------------

    public function setUserRepository(object $repo): void
    {
        // Injected by BotInstance when DB is configured
        $this->dbUser = $repo->findOrCreateByUpdate($this->update);
        $this->dbUserLoaded = true;
    }

    public function user(): ?object
    {
        return $this->dbUser;
    }

    public function step(): ?string
    {
        return $this->dbUser?->step;
    }

    public function setStep(string $step): void
    {
        if ($this->dbUser === null) return;
        $this->dbUser->step = $step;
        $this->dbUser->save();
    }

    public function temp(?string $key = null): mixed
    {
        if ($this->dbUser === null) return null;
        $data = is_array($this->dbUser->temp_data) ? $this->dbUser->temp_data : [];
        return $key === null ? $data : ($data[$key] ?? null);
    }

    public function setTemp(string $key, mixed $value): void
    {
        if ($this->dbUser === null) return;
        $data = is_array($this->dbUser->temp_data) ? $this->dbUser->temp_data : [];
        $data[$key] = $value;
        $this->dbUser->temp_data = $data;
        $this->dbUser->save();
    }

    public function clearFlow(): void
    {
        if ($this->dbUser === null) return;
        $this->dbUser->step = null;
        $this->dbUser->temp_data = null;
        $this->dbUser->save();
    }

    // -------------------------------------------------------------------------
    // i18n
    // -------------------------------------------------------------------------

    /**
     * Resolve the active locale: stored user preference → Telegram client
     * language → the library's configured default locale.
     */
    public function locale(): string
    {
        return $this->dbUser?->language
            ?? $this->from()?->languageCode
            ?? Lang::defaultLocale();
    }

    public function setLocale(string $locale): void
    {
        if ($this->dbUser === null) return;
        $this->dbUser->language = $locale;
        $this->dbUser->save();
    }

    public function t(string $key, array $vars = [], ?string $locale = null): string
    {
        return Lang::get($locale ?? $this->locale(), $key, $vars);
    }
}
