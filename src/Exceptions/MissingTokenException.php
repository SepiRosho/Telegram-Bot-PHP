<?php

namespace Devflow\TelegramBot\Exceptions;

/**
 * Thrown when a bot is created (or, under Laravel, first used) without a
 * token — almost always because the env var is missing from .env, which is
 * the first thing a fresh scaffold hits. Without this the failure surfaced as
 * a bare "Argument #1 must be of type string, null given" TypeError.
 *
 * $envVar names the variable the *caller* reads: the standalone scaffold uses
 * BOT_TOKEN, config/telegram.php under Laravel uses TELEGRAM_BOT_TOKEN.
 */
class MissingTokenException extends \RuntimeException
{
    public function __construct(string $envVar = 'BOT_TOKEN')
    {
        parent::__construct(
            "Bot token is empty. Set {$envVar} in your .env file "
            . '(get one from @BotFather on Telegram), then try again.'
        );
    }
}
