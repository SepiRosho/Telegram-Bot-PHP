<?php

namespace Devflow\TelegramBot\Exceptions;

class TelegramApiException extends \RuntimeException
{
    /**
     * @param array $parameters Telegram's `parameters` object from the error
     *                          response — carries `retry_after` on a 429 and
     *                          `migrate_to_chat_id` when a group is upgraded
     *                          to a supergroup.
     */
    public function __construct(
        string $message,
        private readonly int $telegramErrorCode = 0,
        ?\Throwable $previous = null,
        private readonly array $parameters = [],
    ) {
        parent::__construct($message, $telegramErrorCode, $previous);
    }

    public function telegramErrorCode(): int
    {
        return $this->telegramErrorCode;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Seconds Telegram asked us to wait, when it said so. HttpClient already
     * retries within its own limits, so a non-null value here means the wait
     * exceeded `max_retry_after` or the request carried an upload and could
     * not be replayed.
     */
    public function retryAfter(): ?int
    {
        return isset($this->parameters['retry_after'])
            ? (int) $this->parameters['retry_after']
            : null;
    }

    /** A group that became a supergroup — resend to this chat id instead. */
    public function migrateToChatId(): ?int
    {
        return isset($this->parameters['migrate_to_chat_id'])
            ? (int) $this->parameters['migrate_to_chat_id']
            : null;
    }
}
