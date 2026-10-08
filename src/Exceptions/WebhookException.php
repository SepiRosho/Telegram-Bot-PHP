<?php

namespace Devflow\TelegramBot\Exceptions;

/**
 * A webhook request that must be rejected rather than dispatched. statusCode()
 * is the HTTP status the rejection deserves (403 for a bad secret, 400 for a
 * malformed body), so a framework adapter can answer correctly instead of
 * letting the exception turn into a generic 500.
 */
class WebhookException extends \RuntimeException
{
    public function __construct(string $message = '', private int $statusCode = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
