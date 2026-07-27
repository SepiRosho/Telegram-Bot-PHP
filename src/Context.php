<?php

namespace Devflow\TelegramBot;

use Devflow\TelegramBot\Api\InputFile;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Types\CallbackQuery;
use Devflow\TelegramBot\Types\Chat;
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

    // -------------------------------------------------------------------------
    // Chat type
    // -------------------------------------------------------------------------

    /**
     * The Chat this update happened in, or null for update types that carry no
     * chat (inline queries, poll answers, pre-checkout queries, ...).
     */
    public function chat(): ?Chat
    {
        return $this->update->chat();
    }

    /** 'private' | 'group' | 'supergroup' | 'channel', or null when the update has no chat. */
    public function chatType(): ?string
    {
        return $this->update->chatType();
    }

    public function isPrivate(): bool
    {
        return $this->chatType() === 'private';
    }

    public function isGroup(): bool
    {
        return in_array($this->chatType(), ['group', 'supergroup'], true);
    }

    public function isChannel(): bool
    {
        return $this->chatType() === 'channel';
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

    public function replyWithPhoto(string|InputFile $photo, array $options = []): Message
    {
        return $this->api->sendPhoto($this->chatId(), $photo, $options);
    }

    public function replyWithDocument(string|InputFile $document, array $options = []): Message
    {
        return $this->api->sendDocument($this->chatId(), $document, $options);
    }

    public function replyWithVideo(string|InputFile $video, array $options = []): Message
    {
        return $this->api->sendVideo($this->chatId(), $video, $options);
    }

    public function replyWithAudio(string|InputFile $audio, array $options = []): Message
    {
        return $this->api->sendAudio($this->chatId(), $audio, $options);
    }

    public function replyWithVoice(string|InputFile $voice, array $options = []): Message
    {
        return $this->api->sendVoice($this->chatId(), $voice, $options);
    }

    public function replyWithSticker(string|InputFile $sticker, array $options = []): Message
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

    /**
     * Like editReply(), but swallows Telegram's "message is not modified"
     * error (returns null instead of throwing) and auto-falls back to
     * editMessageCaption() when the target message is media, since
     * editMessageText can't touch a caption.
     */
    public function editReplySafe(string $text, array $options = []): ?Message
    {
        $messageId = $this->update->callbackQuery?->message?->messageId;
        if ($messageId === null) {
            return $this->reply($text, $options);
        }

        $target = $this->update->callbackQuery?->message;

        try {
            if ($target !== null && $this->isMediaMessage($target)) {
                return $this->api->editMessageCaption($this->chatId(), $messageId, $text, $options);
            }
            return $this->api->editMessageText($this->chatId(), $messageId, $text, $options);
        } catch (TelegramApiException $e) {
            if ($this->isNotModifiedError($e)) {
                return null;
            }
            throw $e;
        }
    }

    /** Strip the inline keyboard from the current callback message. Swallows "not modified". */
    public function removeKeyboard(): bool
    {
        $messageId = $this->update->callbackQuery?->message?->messageId ?? $this->message()?->messageId;
        if ($messageId === null) {
            return false;
        }

        try {
            $this->api->editMessageReplyMarkup($this->chatId(), $messageId, ['inline_keyboard' => []]);
            return true;
        } catch (TelegramApiException $e) {
            if ($this->isNotModifiedError($e)) {
                return false;
            }
            throw $e;
        }
    }

    private function isMediaMessage(Message $message): bool
    {
        return $message->photo !== null
            || $message->video !== null
            || $message->audio !== null
            || $message->document !== null
            || $message->voice !== null
            || $message->sticker !== null
            || $message->animation !== null
            || $message->videoNote !== null;
    }

    private function isNotModifiedError(TelegramApiException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'message is not modified');
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
        $locale = $this->dbUser?->language ?? $this->from()?->languageCode;

        return $locale === null || $locale === ''
            ? Lang::defaultLocale()
            : Lang::normalize($locale);
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
