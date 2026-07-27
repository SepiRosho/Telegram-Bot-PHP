<?php

namespace Devflow\TelegramBot\Exceptions;

/**
 * Thrown when Bot::init() is called with an empty token — almost always
 * because BOT_TOKEN is missing from .env, which is the first thing a fresh
 * scaffold hits. Without this the failure surfaced as a bare
 * "Argument #1 must be of type string, null given" TypeError.
 */
class MissingTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Bot token is empty. Set BOT_TOKEN in your .env file '
            . '(get one from @BotFather on Telegram), then try again.'
        );
    }
}
