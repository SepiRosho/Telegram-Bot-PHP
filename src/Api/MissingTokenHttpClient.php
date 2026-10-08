<?php

namespace Devflow\TelegramBot\Api;

use Devflow\TelegramBot\Exceptions\MissingTokenException;

/**
 * Stands in for the real client while no token is configured, so a framework
 * can build the bot (and let handlers register against it) without a token —
 * `composer install`, `package:discover`, `config:cache` and CI all boot the
 * app before any secret exists. The first actual API call is what fails.
 */
class MissingTokenHttpClient implements HttpClientInterface
{
    public function __construct(private string $envVar = 'BOT_TOKEN') {}

    public function post(string $method, array $params = []): mixed
    {
        throw new MissingTokenException($this->envVar);
    }
}
