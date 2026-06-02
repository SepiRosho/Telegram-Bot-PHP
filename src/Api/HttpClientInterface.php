<?php

namespace Devflow\TelegramBot\Api;

interface HttpClientInterface
{
    public function post(string $method, array $params = []): mixed;
}
