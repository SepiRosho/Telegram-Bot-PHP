<?php

namespace Devflow\TelegramBot\Api;

/**
 * Test double for HttpClientInterface — records every call instead of making
 * a real HTTP request, and returns a minimally-valid canned response so
 * Message::fromArray()/User::fromArray() don't choke on missing keys.
 *
 * See Testing\FakeBot / Bot::fake() for the higher-level test harness.
 */
class FakeHttpClient implements HttpClientInterface
{
    private array $calls = [];
    private array $queuedResponses = [];
    private int $nextMessageId = 1;

    /**
     * Queue a canned result for the next call to $method (FIFO). If no
     * response is queued, a generic default is synthesized instead.
     *
     * Queue a Throwable to have it thrown instead of returned — the only way
     * to exercise the paths that matter most (a user who blocked the bot, a
     * 429, a webhook conflict) without a real network.
     */
    public function respond(string $method, mixed $result): void
    {
        $this->queuedResponses[$method][] = $result;
    }

    /** Reads better than respond() when the queued value is an error. */
    public function throw(string $method, \Throwable $e): void
    {
        $this->respond($method, $e);
    }

    public function post(string $method, array $params = []): mixed
    {
        $this->calls[] = ['method' => $method, 'params' => $params];

        if (!empty($this->queuedResponses[$method])) {
            $queued = array_shift($this->queuedResponses[$method]);

            if ($queued instanceof \Throwable) {
                throw $queued;
            }

            return $queued;
        }

        return $this->defaultResult($method, $params);
    }

    /** All recorded calls, in order: [['method' => ..., 'params' => ...], ...]. */
    public function calls(): array
    {
        return $this->calls;
    }

    /** Recorded calls filtered to a single method. */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn(array $c) => $c['method'] === $method));
    }

    private function defaultResult(string $method, array $params): mixed
    {
        return match ($method) {
            'getMe' => [
                'id' => 1, 'is_bot' => true, 'first_name' => 'FakeBot', 'username' => 'fake_bot',
            ],
            'sendMessage', 'sendPhoto', 'sendDocument', 'sendAudio', 'sendVideo', 'sendVoice',
            'sendSticker', 'sendLocation', 'sendVenue', 'sendVideoNote', 'sendAnimation',
            'sendContact', 'sendDice', 'sendPoll', 'copyMessage', 'forwardMessage',
            'editMessageText', 'editMessageCaption', 'editMessageMedia', 'editMessageReplyMarkup',
                => $this->fakeMessage($params),
            'getUpdates' => [],
            'sendMediaGroup' => [$this->fakeMessage($params)],
            'getChatMember' => ['status' => 'member', 'user' => ['id' => $params['user_id'] ?? 0, 'is_bot' => false, 'first_name' => 'Test']],
            'getChat' => ['id' => $params['chat_id'] ?? 0, 'type' => 'private'],
            'getWebhookInfo' => ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0],
            default => true,
        };
    }

    private function fakeMessage(array $params): array
    {
        return array_filter([
            'message_id' => $this->nextMessageId++,
            'date'       => time(),
            'chat'       => ['id' => $params['chat_id'] ?? 0, 'type' => 'private'],
            'from'       => ['id' => 1, 'is_bot' => true, 'first_name' => 'FakeBot'],
            'text'       => $params['text'] ?? null,
            'caption'    => $params['caption'] ?? null,
        ], fn($v) => $v !== null);
    }
}
