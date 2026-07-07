<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Database\Models\Broadcast;
use Devflow\TelegramBot\Database\Models\TelegramUser;

class BroadcastRunCommand
{
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

        $pending = Broadcast::where('status', 'pending')->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->line('No pending broadcasts found.');
            return;
        }

        $this->line("Found {$pending->count()} pending broadcast(s). Rate: {$ratePerSecond} msg/s.\n");

        foreach ($pending as $broadcast) {
            $this->processBroadcast($broadcast, $sleepMicroseconds);
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

        foreach ($users as $user) {
            try {
                Bot::sendMessage($user->chat_id, $broadcast->message, $broadcast->options ?? []);
                $sent++;
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

        $broadcast->status = 'completed';
        $broadcast->sent_count = $sent;
        $broadcast->failed_count = $failed;
        $broadcast->completed_at = \Carbon\Carbon::now();
        $broadcast->save();

        $this->success("Broadcast #{$broadcast->id} complete — sent: {$sent}, failed: {$failed} / {$total}");
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
