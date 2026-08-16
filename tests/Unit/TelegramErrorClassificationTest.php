<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Exceptions\TelegramApiException;
use PHPUnit\Framework\TestCase;

/**
 * Every description below is a real string Telegram returns. The whole feature
 * is only as good as this table, so it is written out verbatim rather than
 * paraphrased.
 */
class TelegramErrorClassificationTest extends TestCase
{
    private function error(string $description, int $code = 400, array $parameters = []): TelegramApiException
    {
        return new TelegramApiException($description, $code, null, $parameters);
    }

    // ─── Chat unavailable — stop sending, mark inactive ───────────────────────

    public static function unavailableProvider(): array
    {
        return [
            'blocked'            => ['Forbidden: bot was blocked by the user', 403],
            'deactivated'        => ['Forbidden: user is deactivated', 403],
            'kicked from group'  => ['Forbidden: bot was kicked from the group chat', 403],
            'kicked supergroup'  => ['Forbidden: bot was kicked from the supergroup chat', 403],
            'not a member'       => ['Forbidden: bot is not a member of the supergroup chat', 403],
            'group deleted'      => ['Forbidden: the group chat was deleted', 403],
            'chat not found'     => ['Bad Request: chat not found', 400],
            'user not found'     => ['Bad Request: user not found', 400],
            'peer id invalid'    => ['Bad Request: PEER_ID_INVALID', 400],
            'cannot initiate'    => ["Forbidden: bot can't initiate conversation with a user", 403],
            'cannot dm bots'     => ["Bad Request: bot can't send messages to bots", 400],
        ];
    }

    /** @dataProvider unavailableProvider */
    public function test_unreachable_chats_are_recognised(string $description, int $code): void
    {
        $e = $this->error($description, $code);

        $this->assertTrue($e->isChatUnavailable(), $description);
        $this->assertTrue($e->isExpected(), $description);
    }

    public function test_the_reported_bug_is_the_headline_case(): void
    {
        $e = $this->error('Forbidden: bot was blocked by the user', 403);

        $this->assertTrue($e->isChatUnavailable());
        $this->assertFalse($e->isTransient(), 'retrying a blocked user can never succeed');
        $this->assertFalse($e->isIgnorable(), 'the recipient still needs deactivating');
    }

    /** An unfamiliar 403 still means "the bot may not post here". */
    public function test_an_unrecognised_403_defaults_to_unavailable(): void
    {
        $this->assertTrue($this->error('Forbidden: something new in 2027', 403)->isChatUnavailable());
    }

    // ─── Permission denied — a different fix from "unreachable" ───────────────

    public static function permissionProvider(): array
    {
        return [
            ['Bad Request: not enough rights to send text messages to the chat'],
            ['Bad Request: have no rights to send a message'],
            ['Bad Request: need administrator rights in the channel chat'],
            ['Bad Request: CHAT_WRITE_FORBIDDEN'],
            ['Bad Request: TOPIC_CLOSED'],
            // Bare Telegram error constants with no human-readable wrapper —
            // matched generically by the *_REQUIRED/*_FORBIDDEN suffix rather
            // than enumerated one by one.
            ['Bad Request: CHAT_ADMIN_REQUIRED'],
            ['Bad Request: USER_NOT_MUTUAL_CONTACT_REQUIRED'],
        ];
    }

    /** @dataProvider permissionProvider */
    public function test_missing_rights_is_not_reported_as_an_unreachable_chat(string $description): void
    {
        $e = $this->error($description, 403);

        $this->assertTrue($e->isPermissionDenied(), $description);
        // Deactivating a user over a group permission problem would drop them
        // from every future broadcast for a reason that has nothing to do with
        // them, so the two must never overlap.
        $this->assertFalse($e->isChatUnavailable(), $description);
        $this->assertTrue($e->isExpected(), $description);
    }

    // ─── Ignorable — no-ops and stale requests ───────────────────────────────

    public static function ignorableProvider(): array
    {
        return [
            ['Bad Request: message is not modified: specified new message content and reply markup are exactly the same'],
            ['Bad Request: query is too old and response timeout expired or query ID is invalid'],
            ['Bad Request: message to edit not found'],
            ['Bad Request: message to delete not found'],
            ['Bad Request: message to be replied not found'],
            ["Bad Request: message can't be deleted for everyone"],
        ];
    }

    /** @dataProvider ignorableProvider */
    public function test_no_op_requests_are_ignorable(string $description): void
    {
        $e = $this->error($description);

        $this->assertTrue($e->isIgnorable(), $description);
        $this->assertTrue($e->isExpected(), $description);
        $this->assertFalse($e->isChatUnavailable(), $description);
    }

    // ─── Rate limits and transient failures ──────────────────────────────────

    public function test_a_429_is_rate_limited_not_a_dead_chat(): void
    {
        $e = $this->error('Too Many Requests: retry after 12', 429, ['retry_after' => 12]);

        $this->assertTrue($e->isRateLimited());
        $this->assertTrue($e->isExpected());
        $this->assertFalse($e->isChatUnavailable());
        $this->assertSame(12, $e->retryAfter());
    }

    public function test_server_and_network_failures_are_transient(): void
    {
        $this->assertTrue($this->error('Bad Gateway', 502)->isTransient());
        $this->assertTrue($this->error('Internal Server Error', 500)->isTransient());
        $this->assertTrue($this->error('cURL error 28: Operation timed out', 0)->isTransient());
        $this->assertTrue($this->error('cURL error 6: Could not resolve host: api.telegram.org', 0)->isTransient());
    }

    // ─── The negative case, which matters most ───────────────────────────────

    public function test_a_genuine_bug_is_not_swallowed_as_expected(): void
    {
        // A malformed request is the bot's own fault: it must keep surfacing
        // loudly, or classification has quietly become an error-suppressor.
        $e = $this->error('Bad Request: message text is empty', 400);

        $this->assertFalse($e->isExpected());
        $this->assertFalse($e->isChatUnavailable());
        $this->assertFalse($e->isIgnorable());
        $this->assertFalse($e->isTransient());
    }

    public function test_an_unparseable_response_is_not_expected(): void
    {
        $this->assertFalse($this->error('Malformed response from Telegram (HTTP 200)', 200)->isExpected());
    }

    public function test_matching_is_case_insensitive(): void
    {
        $this->assertTrue($this->error('FORBIDDEN: BOT WAS BLOCKED BY THE USER', 403)->isChatUnavailable());
    }
}
