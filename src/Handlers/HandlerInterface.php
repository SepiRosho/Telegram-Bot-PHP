<?php

namespace Devflow\TelegramBot\Handlers;

use Devflow\TelegramBot\Context;

interface HandlerInterface
{
    public function handle(Context $ctx): void;
}
