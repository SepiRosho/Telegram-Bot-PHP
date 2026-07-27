<?php

namespace Devflow\TelegramBot\Api;

use Devflow\TelegramBot\Exceptions\TelegramApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient implements HttpClientInterface
{
    private Client $client;
    private int $maxRetries;
    private int $maxRetryAfter;

    public function __construct(string $token, array $options = [])
    {
        $clientOptions = [
            'base_uri' => "https://api.telegram.org/bot{$token}/",
            'timeout' => $options['timeout'] ?? 30,
            // Telegram signals rate limits with a 429 body we need to read, so
            // 4xx/5xx must not become Guzzle exceptions before we get a chance
            // to parse `parameters.retry_after` out of them.
            'http_errors' => false,
        ];

        if (!empty($options['proxy'])) {
            $clientOptions['proxy'] = $options['proxy'];
        }

        $this->maxRetries    = (int) ($options['max_retries'] ?? 2);
        $this->maxRetryAfter = (int) ($options['max_retry_after'] ?? 60);

        $this->client = new Client($clientOptions);
    }

    public function post(string $method, array $params = []): mixed
    {
        $params  = array_filter($params, fn($v) => $v !== null);
        $attempt = 0;

        while (true) {
            try {
                $response = $this->client->post($method, $this->requestOptions($params));
                $status   = $response->getStatusCode();
                $body     = json_decode($response->getBody()->getContents(), true);

                if (!is_array($body)) {
                    throw new TelegramApiException(
                        "Malformed response from Telegram (HTTP {$status}) for method {$method}",
                        $status,
                    );
                }

                if ($body['ok'] ?? false) {
                    return $body['result'];
                }

                $parameters = $body['parameters'] ?? [];
                $retryAfter = isset($parameters['retry_after']) ? (int) $parameters['retry_after'] : null;

                // 429 is less a failure than an instruction: Telegram states
                // exactly how long to wait. Honouring it here means a
                // broadcast loop doesn't have to reimplement backoff, which
                // is what every caller otherwise ends up doing.
                if ($this->shouldRetry($retryAfter, $params, $attempt)) {
                    $attempt++;
                    sleep($retryAfter);
                    continue;
                }

                throw new TelegramApiException(
                    $body['description'] ?? 'Unknown Telegram API error',
                    $body['error_code'] ?? $status,
                    parameters: $parameters,
                );
            } catch (GuzzleException $e) {
                throw new TelegramApiException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * A retry has to re-send the body, but an InputFile opened as a stream is
     * already consumed by the first attempt — so uploads are never retried
     * automatically, and the 429 surfaces to the caller instead.
     */
    private function shouldRetry(?int $retryAfter, array $params, int $attempt): bool
    {
        return $retryAfter !== null
            && $attempt < $this->maxRetries
            && $retryAfter <= $this->maxRetryAfter
            && !$this->hasUpload($params);
    }

    private function requestOptions(array $params): array
    {
        return $this->hasUpload($params)
            ? ['multipart' => $this->toMultipart($params)]
            : ['json' => $params];
    }

    private function hasUpload(array $params): bool
    {
        foreach ($params as $value) {
            if ($value instanceof InputFile) {
                return true;
            }
        }

        return false;
    }

    /**
     * Multipart bodies are flat name/value pairs, so structured parameters
     * (reply_markup, entities, sendMediaGroup's media array) have to be
     * JSON-encoded here — the exact inverse of the 'json' path, where
     * pre-encoding them is the classic double-encoding bug. Doing it at this
     * boundary keeps the caller-facing rule unconditional: always pass PHP
     * arrays, never call json_encode() yourself.
     */
    private function toMultipart(array $params): array
    {
        $parts = [];

        foreach ($params as $name => $value) {
            if ($value instanceof InputFile) {
                $parts[] = array_filter([
                    'name'     => (string) $name,
                    'contents' => $value->open(),
                    'filename' => $value->filename(),
                ], fn($v) => $v !== null);
                continue;
            }

            $parts[] = [
                'name'     => (string) $name,
                'contents' => $this->scalarize($value),
            ];
        }

        return $parts;
    }

    private function scalarize(mixed $value): string
    {
        return match (true) {
            is_array($value) => (string) json_encode($value),
            is_bool($value)  => $value ? 'true' : 'false',
            default          => (string) $value,
        };
    }
}
