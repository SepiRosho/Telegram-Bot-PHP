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
    private bool $retryTransient;
    private float $retryJitter;

    /** @var (callable(int, int, TelegramApiException): int)|null */
    private $retryStrategy;

    /** @var (callable(int, int, string, TelegramApiException): void)|null */
    private $onRetry;

    /** @var callable(int): void */
    private $sleeper;

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

        // Test-only escape hatch: a Guzzle HandlerStack/callable so the retry
        // loop can be exercised against a MockHandler instead of the network.
        // Not documented as a Bot::init() config key.
        if (isset($options['handler'])) {
            $clientOptions['handler'] = $options['handler'];
        }

        $this->maxRetries    = (int) ($options['max_retries'] ?? 2);
        $this->maxRetryAfter = (int) ($options['max_retry_after'] ?? 60);

        // Retrying a 5xx or a network hiccup changes what used to be an
        // immediate throw into a wait, which existing callers may already
        // handle themselves (isTransient()) — opt-in rather than a default
        // behavior change.
        $this->retryTransient = (bool) ($options['retry_transient'] ?? false);

        // Extra randomness on top of whatever wait was computed, as a
        // fraction of it (0.1 = up to +10%) — smooths out a thundering herd
        // of workers that all got rate-limited at the same instant and would
        // otherwise all retry on the exact same second.
        $this->retryJitter = (float) ($options['retry_jitter'] ?? 0.0);

        // Overrides the wait entirely when set: fn(attempt, baseWaitSeconds,
        // TelegramApiException) => seconds. Runs before jitter is applied, so
        // a caller doing their own jitter/capping can just return their
        // number and leave retry_jitter at 0.
        $this->retryStrategy = $options['retry_strategy'] ?? null;

        // Pure observer, run right before sleeping — logging/metrics only,
        // it cannot change the wait: fn(attempt, waitSeconds, method,
        // TelegramApiException).
        $this->onRetry = $options['on_retry'] ?? null;

        // The actual wait, swappable so an app built on Fibers/ReactPHP/Swoole
        // can suspend instead of blocking the process: fn(seconds) => void.
        // The library itself stays synchronous by default (a plain sleep()) —
        // this only makes *that one call* replaceable, not the whole request
        // cycle async.
        $this->sleeper = $options['sleeper'] ?? static function (int $seconds): void {
            sleep($seconds);
        };

        $this->client = new Client($clientOptions);
    }

    public function post(string $method, array $params = []): mixed
    {
        $params  = array_filter($params, fn($v) => $v !== null);
        $attempt = 0;

        while (true) {
            try {
                // Rebuilt on every attempt rather than once outside the loop:
                // an InputFile's open() returns a fresh handle/string each
                // call, so a retry sends a fresh body instead of an already-
                // consumed stream — uploads are retried exactly like any
                // other request.
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

                $exception = new TelegramApiException(
                    $body['description'] ?? 'Unknown Telegram API error',
                    $body['error_code'] ?? $status,
                    parameters: $body['parameters'] ?? [],
                );
            } catch (GuzzleException $e) {
                $exception = new TelegramApiException($e->getMessage(), $e->getCode(), $e);
            }

            if (!$this->shouldRetry($exception, $attempt)) {
                throw $exception;
            }

            $wait = $this->waitSeconds($exception, $attempt);

            if ($this->onRetry !== null) {
                ($this->onRetry)($attempt, $wait, $method, $exception);
            }

            ($this->sleeper)($wait);
            $attempt++;
        }
    }

    /**
     * 429 is less a failure than an instruction: Telegram states exactly how
     * long to wait. Honouring it here means a broadcast loop doesn't have to
     * reimplement backoff, which is what every caller otherwise ends up
     * doing. A plain 429 with no `retry_after` in the body, and — opt-in via
     * retry_transient — a 5xx or network-level failure, fall back to the same
     * exponential schedule.
     */
    private function shouldRetry(TelegramApiException $e, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        if ($e->retryAfter() !== null) {
            return $e->retryAfter() <= $this->maxRetryAfter;
        }

        if ($e->telegramErrorCode() === 429 || ($this->retryTransient && $e->isTransient())) {
            return $this->backoffSeconds($attempt) <= $this->maxRetryAfter;
        }

        return false;
    }

    private function waitSeconds(TelegramApiException $e, int $attempt): int
    {
        $wait = $e->retryAfter() ?? $this->backoffSeconds($attempt);

        if ($this->retryStrategy !== null) {
            $wait = (int) ($this->retryStrategy)($attempt, $wait, $e);
        }

        if ($this->retryJitter > 0) {
            $wait += (int) round($wait * $this->retryJitter * (mt_rand() / mt_getrandmax()));
        }

        return max(0, $wait);
    }

    /** 1s, 2s, 4s, 8s, … capped at max_retry_after — used whenever Telegram didn't hand us a number. */
    private function backoffSeconds(int $attempt): int
    {
        return min($this->maxRetryAfter, 2 ** $attempt);
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
