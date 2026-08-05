<?php

namespace Devflow\TelegramBot\Exceptions;

class TelegramApiException extends \RuntimeException
{
    /**
     * A user who blocked the bot, a callback query answered a second too late,
     * an edit that changed nothing — Telegram reports all of these exactly the
     * way it reports a genuinely malformed request: `ok: false` plus a
     * human-readable description. Without a way to tell them apart, callers
     * treat every one as a crash, which is how a bot ends up retrying a send
     * that can never succeed.
     *
     * Matching is on the description text, not `error_code`, because the codes
     * are far too coarse: 403 covers blocked, kicked and deactivated alike,
     * and 400 covers everything from a stale button press to a real bug.
     */
    private const CHAT_UNAVAILABLE = [
        'bot was blocked by the user',
        'user is deactivated',
        'user is deleted',
        'bot was kicked',
        'bot is not a member',
        'the group chat was deleted',
        'chat not found',
        'user not found',
        'peer_id_invalid',
        "bot can't initiate conversation with a user",
        "bot can't send messages to bots",
    ];

    /** The chat exists and the bot is in it — it just isn't allowed to post. */
    private const PERMISSION_DENIED = [
        'not enough rights',
        'have no rights to send a message',
        'need administrator rights',
        'chat_write_forbidden',
        'chat_send_plain_forbidden',
        'chat_send_media_forbidden',
        'topic_closed',
        'the message can not be forwarded',
        'chat_restricted',
    ];

    /**
     * The request was a no-op or arrived too late. Nothing is wrong, nothing
     * needs retrying, and nothing is worth waking an operator over.
     */
    private const IGNORABLE = [
        'message is not modified',
        'query is too old',
        'query id is invalid',
        'message to edit not found',
        'message to delete not found',
        'message to be replied not found',
        'message to forward not found',
        'message to copy not found',
        "message can't be deleted",
        'message identifier is not specified',
        'reply message not found',
        'callback_query_id_invalid',
        'message_id_invalid',
    ];

    /** Telegram or the network hiccupped — the same request may well work next time. */
    private const TRANSIENT = [
        'bad gateway',
        'gateway timeout',
        'internal server error',
        'service unavailable',
        'restart',
        'curl error',
        'timed out',
        'timeout was reached',
        'could not resolve host',
        'connection refused',
        'connection reset',
        'empty reply from server',
    ];

    /**
     * @param array $parameters Telegram's `parameters` object from the error
     *                          response — carries `retry_after` on a 429 and
     *                          `migrate_to_chat_id` when a group is upgraded
     *                          to a supergroup.
     */
    public function __construct(
        string $message,
        private readonly int $telegramErrorCode = 0,
        ?\Throwable $previous = null,
        private readonly array $parameters = [],
    ) {
        parent::__construct($message, $telegramErrorCode, $previous);
    }

    public function telegramErrorCode(): int
    {
        return $this->telegramErrorCode;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Seconds Telegram asked us to wait, when it said so. HttpClient already
     * retries within its own limits, so a non-null value here means the wait
     * exceeded `max_retry_after` or the request carried an upload and could
     * not be replayed.
     */
    public function retryAfter(): ?int
    {
        return isset($this->parameters['retry_after'])
            ? (int) $this->parameters['retry_after']
            : null;
    }

    /** A group that became a supergroup — resend to this chat id instead. */
    public function migrateToChatId(): ?int
    {
        return isset($this->parameters['migrate_to_chat_id'])
            ? (int) $this->parameters['migrate_to_chat_id']
            : null;
    }

    // -------------------------------------------------------------------------
    // Classification — what the caller should actually do about this
    // -------------------------------------------------------------------------

    /**
     * This chat can never receive a message again without the user acting
     * first: they blocked the bot, deleted their account, or removed the bot
     * from the group. Stop sending to it and mark it inactive — retrying is
     * guaranteed to fail forever.
     */
    public function isChatUnavailable(): bool
    {
        // A bare 403 with an unrecognised description still means "the bot may
        // not post here", so it falls through to unavailable — unless it is
        // specifically about missing rights, which is a different fix.
        if ($this->isPermissionDenied()) {
            return false;
        }

        return $this->matches(self::CHAT_UNAVAILABLE) || $this->telegramErrorCode === 403;
    }

    /** The bot is in the chat but lacks the rights for this particular action. */
    public function isPermissionDenied(): bool
    {
        return $this->matches(self::PERMISSION_DENIED);
    }

    /** A no-op or too-late request. Safe to swallow entirely. */
    public function isIgnorable(): bool
    {
        return $this->matches(self::IGNORABLE);
    }

    public function isRateLimited(): bool
    {
        return $this->telegramErrorCode === 429 || $this->retryAfter() !== null;
    }

    /** Telegram or the network is having a moment — backing off is worthwhile. */
    public function isTransient(): bool
    {
        return $this->telegramErrorCode >= 500 || $this->matches(self::TRANSIENT);
    }

    /**
     * A condition Telegram itself considers routine, rather than a defect in
     * your bot. The polling loop uses this to decide whether an error deserves
     * a red ✗ and a backoff, or just a note and the next update.
     */
    public function isExpected(): bool
    {
        return $this->isIgnorable()
            || $this->isRateLimited()
            || $this->isPermissionDenied()
            || $this->isChatUnavailable();
    }

    /**
     * @param list<string> $needles Lowercase fragments of Telegram descriptions.
     */
    private function matches(array $needles): bool
    {
        $description = strtolower($this->getMessage());

        foreach ($needles as $needle) {
            if (str_contains($description, $needle)) {
                return true;
            }
        }

        return false;
    }
}
