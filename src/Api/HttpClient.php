<?php

namespace Devflow\TelegramBot\Api;

use Devflow\TelegramBot\Exceptions\TelegramApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient implements HttpClientInterface
{
    private Client $client;

    public function __construct(string $token, array $options = [])
    {
        $clientOptions = [
            'base_uri' => "https://api.telegram.org/bot{$token}/",
            'timeout' => 30,
        ];

        if (!empty($options['proxy'])) {
            $clientOptions['proxy'] = $options['proxy'];
        }

        $this->client = new Client($clientOptions);
    }

    public function post(string $method, array $params = []): mixed
    {
        try {
            $response = $this->client->post($method, [
                'json' => array_filter($params, fn($v) => $v !== null),
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!($body['ok'] ?? false)) {
                throw new TelegramApiException(
                    $body['description'] ?? 'Unknown Telegram API error',
                    $body['error_code'] ?? 0,
                );
            }

            return $body['result'];
        } catch (GuzzleException $e) {
            throw new TelegramApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
