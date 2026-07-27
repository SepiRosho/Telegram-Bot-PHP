<?php

namespace Devflow\TelegramBot\Console\Commands\Concerns;

/**
 * Locating the project root, loading its .env and requiring bootstrap/app.php
 * is the same three steps in front of every command that has to run *inside*
 * a generated project. Requires the using class to provide an error() helper.
 */
trait BootsProject
{
    private function projectRoot(): string
    {
        return (string) getcwd();
    }

    private function bootstrapPath(): string
    {
        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
    }

    private function hasProjectBootstrap(): bool
    {
        return file_exists($this->bootstrapPath());
    }

    /** vlucas/phpdotenv is only a suggestion of illuminate/support, so its absence is not fatal. */
    private function loadProjectEnv(): void
    {
        $root = $this->projectRoot();

        if (file_exists($root . DIRECTORY_SEPARATOR . '.env') && class_exists('Dotenv\Dotenv')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }
    }

    private function requireProjectBootstrap(): void
    {
        if (!$this->hasProjectBootstrap()) {
            $this->error('bootstrap/app.php not found. Run this command from your project root.');
            exit(1);
        }

        $this->loadProjectEnv();

        // A misconfigured project (empty BOT_TOKEN being the usual one) would
        // otherwise dump a full stack trace out of bootstrap/app.php, burying
        // the one line that says what to fix.
        try {
            require $this->bootstrapPath();
        } catch (\Throwable $e) {
            $this->error('bootstrap/app.php failed to load: ' . $e->getMessage());
            exit(1);
        }
    }
}
