<?php

namespace Devflow\TelegramBot\Console\Commands\Concerns;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Shared by every DB-touching console command so a bad .env produces one
 * readable line instead of an uncaught PDOException stack trace. Requires
 * the using class to provide line()/error() output helpers.
 */
trait ReportsDatabaseErrors
{
    private function failOnDatabaseError(\PDOException $e): never
    {
        $this->error($this->databaseConnectionErrorMessage());
        $this->line("Details: {$e->getMessage()}");
        exit(1);
    }

    private function databaseConnectionErrorMessage(): string
    {
        $config = $this->resolvedDbConfig();

        $database = $config['database'] ?? 'unknown';
        $host     = $config['host'] ?? 'unknown';
        $port     = $config['port'] ?? null;
        $target   = ($port !== null && $port !== '') ? "{$host}:{$port}" : (string) $host;

        return "Could not connect to database \"{$database}\" at {$target} — check DB_DATABASE / DB_HOST / DB_USERNAME / DB_PASSWORD in your .env";
    }

    /** @return array<string, mixed> */
    private function resolvedDbConfig(): array
    {
        try {
            return Capsule::connection()->getConfig();
        } catch (\Throwable) {
            return [];
        }
    }
}
