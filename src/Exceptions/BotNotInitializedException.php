<?php

namespace Devflow\TelegramBot\Exceptions;

class BotNotInitializedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Bot has not been initialized. Call Bot::init($token) first.');
    }
}
