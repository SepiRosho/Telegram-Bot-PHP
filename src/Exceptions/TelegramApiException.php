<?php

namespace Devflow\TelegramBot\Exceptions;

class TelegramApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $telegramErrorCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $telegramErrorCode, $previous);
    }

    public function telegramErrorCode(): int
    {
        return $this->telegramErrorCode;
    }
}
