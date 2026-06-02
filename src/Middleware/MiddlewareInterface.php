<?php

namespace Devflow\TelegramBot\Middleware;

use Devflow\TelegramBot\Context;

interface MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): void;
}
