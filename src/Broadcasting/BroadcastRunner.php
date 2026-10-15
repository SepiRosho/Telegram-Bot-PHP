<?php

namespace Devflow\TelegramBot\Broadcasting;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Database\Models\Broadcast;
use Devflow\TelegramBot\Database\Models\TelegramUser;
use Devflow\TelegramBot\Exceptions\TelegramApiException;

/**
 * Sends every pending `telegram_broadcasts` row to every active, unbanned user.
 *
 * The work behind both `devflow broadcast:run` and `php artisan telegram:broadcast`;
 * each command only supplies a bot and somewhere to print progress.
 */
class BroadcastRunner
{
    /** @var \Closure(string $kind, string $message): void  $kind is 'line' or 'success' */
    private \Closure $output;

    /** @param callable(string, string): void $output */
    public function __construct(private BotInstance $bot, callable $output)
    {
        $this->output = \Closure::fromCallable($output);
    }

    /**
     * Rate is clamped to 1–30 msg/s: Telegram's hard limit is about 30/s, so the
     * default of 25 leaves a buffer.
     *
     * @return int number of broadcasts processed
     */
    public function runPending(int $ratePerSecond = 25): int
    {
        $ratePerSecond = max(1, min(30, $ratePerSecond));
        $sleepMicroseconds = (int) (1_000_000 / $ratePerSecond);

        $pending = Broadcast::where('status', 'pending')->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->say('line', 'No pending broadcasts found.');
            return 0;
        }

        $this->say('line', "Found {$pending->count()} pending broadcast(s). Rate: {$ratePerSecond} msg/s.\n");

        foreach ($pending as $broadcast) {
            $this->process($broadcast, $sleepMicroseconds);
        }

        return $pending->count();
    }

    private function process(Broadcast $broadcast, int $sleepMicroseconds): void
    {
        $this->say('line', "▶ Broadcast #{$broadcast->id}: starting...");

        $broadcast->status = 'running';
        $broadcast->started_at = \Carbon\Carbon::now();
        $broadcast->save();

        $users = TelegramUser::where('is_active', 1)
            ->where('is_banned', 0)
            ->select(['chat_id'])
            ->get();

        $total = $users->count();
        $broadcast->total_recipients = $total;
        $broadcast->save();

        $sent = 0;
        $failed = 0;
        $deactivated = 0;

        foreach ($users as $user) {
            try {
                $this->sendOne($broadcast, $user->chat_id);
                $sent++;
            } catch (TelegramApiException $e) {
                $failed++;

                // What `is_active` is for. A user who blocked the bot is
                // otherwise still in the recipient set for every future
                // broadcast, so the list only ever grows slower — each run
                // paying again for sends that can never succeed. /start sets
                // the flag back to true if they return.
                if ($e->isChatUnavailable()) {
                    TelegramUser::where('chat_id', $user->chat_id)->update(['is_active' => 0]);
                    $deactivated++;
                }
            } catch (\Throwable) {
                $failed++;
            }

            usleep($sleepMicroseconds);

            // Flush progress to DB every 100 sends so the admin panel can track live
            if (($sent + $failed) % 100 === 0) {
                $broadcast->sent_count = $sent;
                $broadcast->failed_count = $failed;
                $broadcast->save();
            }
        }

        // A broadcast that reached zero recipients did not partially work —
        // every send failed, so the documented 'failed' status (otherwise
        // unreachable through this command) belongs here, not 'completed'.
        $broadcast->status = ($total > 0 && $sent === 0) ? 'failed' : 'completed';
        $broadcast->sent_count = $sent;
        $broadcast->failed_count = $failed;
        $broadcast->completed_at = \Carbon\Carbon::now();
        $broadcast->save();

        $note = $deactivated > 0
            ? " ({$deactivated} unreachable, marked inactive)"
            : '';

        $this->say('success', "Broadcast #{$broadcast->id} complete — sent: {$sent}, failed: {$failed}{$note} / {$total}");

        if ($broadcast->notify_chat_id) {
            try {
                $this->bot->sendMessage(
                    $broadcast->notify_chat_id,
                    "📣 Broadcast #{$broadcast->id} complete\nSent: {$sent}\nFailed: {$failed}\nTotal: {$total}",
                );
            } catch (\Throwable) {
                // Don't let a failed notification mark an otherwise-successful broadcast as an error.
            }
        }
    }

    /** Dispatch a single recipient's message, honoring the broadcast's `type`. */
    private function sendOne(Broadcast $broadcast, int|string $chatId): void
    {
        $options = $broadcast->options ?? [];

        match ($broadcast->type) {
            'photo'     => $this->bot->sendPhoto($chatId, $broadcast->media, $options),
            'document'  => $this->bot->sendDocument($chatId, $broadcast->media, $options),
            'video'     => $this->bot->sendVideo($chatId, $broadcast->media, $options),
            'audio'     => $this->bot->sendAudio($chatId, $broadcast->media, $options),
            'voice'     => $this->bot->sendVoice($chatId, $broadcast->media, $options),
            'animation' => $this->bot->sendAnimation($chatId, $broadcast->media, $options),
            'copy'      => $this->bot->copyMessage(
                $chatId,
                $options['from_chat_id'],
                $options['message_id'],
                array_diff_key($options, ['from_chat_id' => null, 'message_id' => null]),
            ),
            default     => $this->bot->sendMessage($chatId, $broadcast->message, $options),
        };
    }

    private function say(string $kind, string $message): void
    {
        ($this->output)($kind, $message);
    }
}
