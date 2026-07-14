<?php

namespace Devflow\TelegramBot\Middleware;

use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Database\Models\BotSetting;
use Devflow\TelegramBot\Support\Keyboard;

/**
 * Gate access behind joining one or more channels — the classic "join our
 * channel(s) first" pattern. Membership is checked via getChatMember() and
 * cached (in bot_settings, TTL-based) so it doesn't cost one API call per
 * channel on every single update.
 *
 * Bot::use(new ForceJoinMiddleware(['@my_channel', '@my_group']));
 * Bot::onCallbackQuery('force_join_verify', $forceJoin->verifyCallback());
 */
class ForceJoinMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $channels,
        private int $cacheTtl = 60,
        private string $message = "Please join the channel(s) below first, then tap \u{2705} I've joined.",
        private string $verifyCallbackData = 'force_join_verify',
    ) {}

    public function handle(Context $ctx, callable $next): void
    {
        $userId = $ctx->userId();

        if ($userId === 0 || $this->channels === []) {
            $next($ctx);
            return;
        }

        $notJoined = $this->notJoinedChannels($ctx, $userId);

        if ($notJoined === []) {
            $next($ctx);
            return;
        }

        $ctx->reply($this->message, ['reply_markup' => $this->keyboard($notJoined)]);
    }

    /** A ready-made handler for the "✅ I've joined" verify button. */
    public function verifyCallback(): callable
    {
        return function (Context $ctx): void {
            $userId = $ctx->userId();

            foreach ($this->channels as $channel) {
                BotSetting::forget($this->cacheKey($userId, $channel));
            }

            if ($this->notJoinedChannels($ctx, $userId) === []) {
                $ctx->answerCallback("\u{2705} Verified — thanks for joining!");
                $ctx->editReplySafe("\u{2705} You're all set. Send /start to continue.");
                return;
            }

            $ctx->answerCallback('Still missing a channel — please join all of them first.', true);
        };
    }

    private function notJoinedChannels(Context $ctx, int $userId): array
    {
        return array_values(array_filter(
            $this->channels,
            fn(string $channel) => !$this->isMember($ctx, $userId, $channel),
        ));
    }

    private function isMember(Context $ctx, int $userId, string $channel): bool
    {
        $cacheKey = $this->cacheKey($userId, $channel);
        $cached = json_decode((string) BotSetting::get($cacheKey, ''), true);

        if (is_array($cached) && ($cached['ts'] ?? 0) > time() - $this->cacheTtl) {
            return (bool) $cached['joined'];
        }

        try {
            $member = $ctx->api()->getChatMember($channel, $userId);
            $joined = in_array($member['status'] ?? '', ['member', 'administrator', 'creator'], true);
        } catch (\Throwable) {
            $joined = false;
        }

        BotSetting::set($cacheKey, json_encode(['joined' => $joined, 'ts' => time()]));

        return $joined;
    }

    private function cacheKey(int $userId, string $channel): string
    {
        return "force_join:{$userId}:{$channel}";
    }

    private function keyboard(array $channels): array
    {
        $rows = array_map(
            fn(string $channel) => [Keyboard::url('➕ Join ' . ltrim($channel, '@'), $this->channelUrl($channel))],
            $channels,
        );
        $rows[] = [Keyboard::button("\u{2705} I've joined", $this->verifyCallbackData)];

        return Keyboard::inline($rows);
    }

    private function channelUrl(string $channel): string
    {
        if (str_starts_with($channel, 'http://') || str_starts_with($channel, 'https://')) {
            return $channel;
        }

        return 'https://t.me/' . ltrim($channel, '@');
    }
}
