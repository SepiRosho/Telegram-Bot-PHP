<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Console\Commands\Concerns\BootsProject;

/**
 * Brings an existing project's scaffold up to date after a `composer update`
 * bumps the library version. Every check is independent and safe to re-run:
 * filesystem additions (a directory or file that doesn't exist yet) are
 * applied automatically since there's nothing to overwrite, while anything
 * that would mean editing a file the developer has almost certainly already
 * customized (bootstrap/app.php) is only ever reported, with the exact
 * snippet to paste in by hand — the same reasoning DoctorCommand uses for
 * read-only diagnostics, one step further.
 *
 * There's no version cutoff baked in: it re-verifies the full current
 * checklist every run, so a project on any older version converges to the
 * same end state. Checks introduced here start from what shipped in v1.15.0
 * (lang_auto_fallback, Support\Emoji, app/Keyboards); add to the checklist
 * as future versions introduce more scaffold-level changes.
 */
class UpgradeCommand
{
    use BootsProject;

    private bool $dryRun = false;
    private int $applied = 0;
    private int $pending = 0;

    public function execute(array $args): void
    {
        foreach ($args as $arg) {
            if ($arg === '--dry-run') {
                $this->dryRun = true;
                continue;
            }

            $this->error("Unknown option: {$arg}");
            $this->line('  Usage: vendor/bin/devflow upgrade [--dry-run]');
            exit(1);
        }

        if (!$this->hasProjectBootstrap()) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        echo "\n\033[1mdevflow upgrade\033[0m — checking this project's scaffold\n\n";

        $this->checkKeyboardsDirectory();
        $this->checkEmojisFile();
        $this->checkEnvKeys();
        $this->checkBootstrapWiring();
        $this->checkAiManifest();

        $this->summary();
    }

    // ─── Checks ─────────────────────────────────────────────────────────────

    private function checkKeyboardsDirectory(): void
    {
        $dir = $this->projectRoot() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Keyboards';

        if (is_dir($dir)) {
            $this->pass('app/Keyboards/ exists');
            return;
        }

        $this->applyOrReport('app/Keyboards/ is missing (target directory for `make:keyboard`)', function () use ($dir) {
            mkdir($dir, 0755, true);
            file_put_contents($dir . DIRECTORY_SEPARATOR . '.gitkeep', '');
        });
    }

    private function checkEmojisFile(): void
    {
        $file = $this->projectRoot() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Emojis.php';

        if (file_exists($file)) {
            $this->pass('app/Emojis.php exists');
            return;
        }

        $this->applyOrReport(
            'app/Emojis.php is missing (registry consumed by Support\\Emoji)',
            fn() => file_put_contents($file, $this->emojisFileTemplate()),
        );
    }

    private function checkEnvKeys(): void
    {
        foreach (['.env', '.env.example'] as $filename) {
            $path = $this->projectRoot() . DIRECTORY_SEPARATOR . $filename;

            if (!file_exists($path)) {
                continue;
            }

            $contents = (string) file_get_contents($path);
            $missing  = [];

            if (!preg_match('/^LANGUAGE_CODE=/m', $contents)) {
                $missing[] = 'LANGUAGE_CODE=auto';
            }
            if (!preg_match('/^LANG_AUTO_FALLBACK=/m', $contents)) {
                $missing[] = 'LANG_AUTO_FALLBACK=false';
            }

            if ($missing === []) {
                $this->pass("{$filename} has LANGUAGE_CODE / LANG_AUTO_FALLBACK");
                continue;
            }

            $this->applyOrReport(
                "{$filename} is missing " . implode(' / ', array_map(fn(string $l) => explode('=', $l)[0], $missing)),
                fn() => file_put_contents($path, rtrim($contents) . "\n" . implode("\n", $missing) . "\n"),
            );
        }
    }

    /**
     * Editing bootstrap/app.php is never auto-applied — of every scaffolded
     * file, it's the one most likely to have been hand-modified by the time
     * a bot's been running for a while, and a regex-based patch is exactly
     * the kind of "clever" edit that's more likely to corrupt a real config
     * array than help it.
     */
    private function checkBootstrapWiring(): void
    {
        $contents = (string) file_get_contents($this->bootstrapPath());

        $markers = [
            'language_code'       => "'language_code'      => env('LANGUAGE_CODE', 'auto'),",
            'lang_auto_fallback'  => "'lang_auto_fallback' => filter_var(env('LANG_AUTO_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),",
            'user_defaults'       => "'user_defaults'      => fn (\\Devflow\\TelegramBot\\Types\\Update \$update): array => [\n        'role' => env('ADMIN_CHAT_ID') && (string) \$update->message?->from?->id === trim((string) env('ADMIN_CHAT_ID'))\n            ? 'superadmin'\n            : 'user',\n    ],",
            'Emoji::registerMany' => "\\Devflow\\TelegramBot\\Support\\Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');",
        ];

        $missing = [];
        foreach ($markers as $key => $snippet) {
            if (!str_contains($contents, $key)) {
                $missing[$key] = $snippet;
            }
        }

        if ($missing === []) {
            $this->pass('bootstrap/app.php already wires language_code / lang_auto_fallback / user_defaults / Emoji');
            return;
        }

        $this->pending++;
        $this->warn('bootstrap/app.php is missing (add by hand — see docs):');
        foreach ($missing as $key => $snippet) {
            $this->line("      \033[36m{$key}\033[0m");
            foreach (explode("\n", $snippet) as $line) {
                $this->line("        {$line}");
            }
        }
    }

    /**
     * Always safe, so it isn't part of the applied/pending tally below: this
     * file is generated by reflection and never hand-edited, so refreshing
     * it is routine maintenance rather than a fix for something broken.
     */
    private function checkAiManifest(): void
    {
        if ($this->dryRun) {
            $this->line('  ~ .ai/api.json (skipped — dry run; would be regenerated)');
            return;
        }

        $result = (new AiManifestCommand())->writeTo($this->projectRoot());

        $result === null
            ? $this->warn('.ai/api.json could not be regenerated')
            : $this->pass(".ai/api.json regenerated ({$result})");
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function applyOrReport(string $description, callable $apply): void
    {
        if ($this->dryRun) {
            $this->warn("{$description} — would fix (dry run)");
            $this->pending++;
            return;
        }

        $apply();
        $this->success("Fixed: {$description}");
        $this->applied++;
    }

    private function emojisFileTemplate(): string
    {
        return <<<'PHP'
        <?php

        // Premium (custom) emoji, registered by name so you can write ":fire:"
        // in message text instead of the raw <tg-emoji>/tg://emoji markup Telegram
        // needs to show one with a fallback glyph for clients that can't render it.
        // Grab the emoji-id by forwarding a message containing it to @userinfobot,
        // or from getCustomEmojiStickers in the Bot API.
        //
        // Loaded once in bootstrap/app.php via Emoji::registerMany(). See docs/16-premium-emoji.md.
        //
        // return [
        //     'fire' => ['id' => '5368324170671202286', 'fallback' => '🔥'],
        // ];

        return [];
        PHP;
    }

    // ─── Output ─────────────────────────────────────────────────────────────

    private function summary(): void
    {
        echo "\n";

        if ($this->applied === 0 && $this->pending === 0) {
            echo "\033[32m✓ Already up to date — nothing to do.\033[0m\n\n";
            return;
        }

        $parts = [];
        if ($this->applied > 0) {
            $parts[] = "\033[32m{$this->applied} fix(es) applied\033[0m";
        }
        if ($this->pending > 0) {
            $parts[] = "\033[33m{$this->pending} item(s) need manual attention\033[0m";
        }

        echo implode(', ', $parts) . "\n\n";

        if ($this->pending > 0) {
            $this->line('After making the manual changes above, run `vendor/bin/devflow doctor` to confirm everything still loads.');
        }

        $this->line('Schema changes (new columns/tables) ship as migrations — run `vendor/bin/devflow migrate` too.');
        echo "\n";
    }

    private function pass(string $msg): void
    {
        echo "  \033[32m✓\033[0m {$msg}\n";
    }

    private function warn(string $msg): void
    {
        echo "  \033[33m!\033[0m {$msg}\n";
    }

    private function success(string $msg): void
    {
        echo "  \033[32m✓\033[0m {$msg}\n";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
