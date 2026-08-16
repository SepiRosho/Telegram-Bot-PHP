<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\Concerns\ReportsDatabaseErrors;
use Devflow\TelegramBot\Database\Models\Broadcast;
use Devflow\TelegramBot\Database\Models\TelegramUser;
use Devflow\TelegramBot\Exceptions\TelegramApiException;

class BroadcastRunCommand
{
    use ReportsDatabaseErrors;

    public function execute(array $args): void
    {
        $cwd = getcwd();
        $bootstrap = $cwd . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (!file_exists($bootstrap)) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        $envFile = $cwd . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile) && class_exists('Dotenv\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($cwd);
            $dotenv->safeLoad();
        }

        require $bootstrap;

        // Rate: 1–30 msgs/s, default 25. Gives a buffer below Telegram's 30 msg/s hard limit.
        $ratePerSecond = max(1, min(30, (int) ($_ENV['BROADCAST_RATE'] ?? $_SERVER['BROADCAST_RATE'] ?? getenv('BROADCAST_RATE') ?: 25)));
        $sleepMicroseconds = (int) (1_000_000 / $ratePerSecond);

        try {
            $pending = Broadcast::where('status', 'pending')->orderBy('id')->get();

            if ($pending->isEmpty()) {
                $this->line('No pending broadcasts found.');
                return;
            }

            $this->line("Found {$pending->count()} pending broadcast(s). Rate: {$ratePerSecond} msg/s.\n");

            foreach ($pending as $broadcast) {
                $this->processBroadcast($broadcast, $sleepMicroseconds);
            }
        } catch (\PDOException $e) {
            $this->failOnDatabaseError($e);
        }
    }

    private function processBroadcast(Broadcast $broadcast, int $sleepMicroseconds): void
    {
        $this->line("▶ Broadcast #{$broadcast->id}: starting...");

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

        $this->success("Broadcast #{$broadcast->id} complete — sent: {$sent}, failed: {$failed}{$note} / {$total}");

        if ($broadcast->notify_chat_id) {
            try {
                Bot::sendMessage(
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
            'photo'     => Bot::sendPhoto($chatId, $broadcast->media, $options),
            'document'  => Bot::sendDocument($chatId, $broadcast->media, $options),
            'video'     => Bot::sendVideo($chatId, $broadcast->media, $options),
            'audio'     => Bot::sendAudio($chatId, $broadcast->media, $options),
            'voice'     => Bot::sendVoice($chatId, $broadcast->media, $options),
            'animation' => Bot::sendAnimation($chatId, $broadcast->media, $options),
            'copy'      => Bot::copyMessage(
                $chatId,
                $options['from_chat_id'],
                $options['message_id'],
                array_diff_key($options, ['from_chat_id' => null, 'message_id' => null]),
            ),
            default     => Bot::sendMessage($chatId, $broadcast->message, $options),
        };
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
