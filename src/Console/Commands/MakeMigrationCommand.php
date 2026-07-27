<?php

namespace Devflow\TelegramBot\Console\Commands;

/**
 * Generates a timestamped migration in the project's database/migrations/.
 *
 * Deliberately not a MakeCommand subclass: those write one PascalCase class
 * file per name, while migrations are anonymous classes in snake_case files
 * whose timestamp prefix is what orders them.
 *
 *   devflow make:migration create_orders_table
 *   devflow make:migration add_phone_to_telegram_users_table
 *
 * The naming convention is load-bearing — `migrate:status` parses
 * `create_<table>_table` to detect tables that exist but were never tracked.
 */
class MakeMigrationCommand
{
    public function execute(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: vendor/bin/devflow make:migration <snake_case_name>');
            $this->line('  e.g. vendor/bin/devflow make:migration create_orders_table');
            exit(1);
        }

        if (!$this->isValidName($name)) {
            $this->error('Migration name must be snake_case (e.g. create_orders_table).');
            exit(1);
        }

        $dir = getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->error("Could not create directory: {$dir}");
            exit(1);
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $existing) {
            // The timestamp prefix differs every run, so a plain file_exists()
            // would happily create a second migration for the same change.
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($name, '/') . '$/', basename($existing, '.php'))) {
                $this->error('A migration named ' . basename($existing) . ' already exists.');
                exit(1);
            }
        }

        $filename = date('Y_m_d_His') . '_' . $name . '.php';
        $path     = $dir . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($path, $this->stub($name));

        $this->success("Migration created: database/migrations/{$filename}");
        $this->line('Run it with: vendor/bin/devflow migrate');
    }

    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $name);
    }

    private function stub(string $name): string
    {
        $table = $this->guessTable($name);

        return match (true) {
            str_starts_with($name, 'create_') && $table !== null => $this->createStub($table),
            $table !== null                                      => $this->alterStub($table),
            default                                              => $this->blankStub(),
        };
    }

    /**
     * Pulls the table out of the two conventional shapes —
     * `create_<table>_table` and `<verb>_<something>_to_<table>_table`.
     */
    private function guessTable(string $name): ?string
    {
        if (preg_match('/^create_(.+)_table$/', $name, $m)) {
            return $m[1];
        }

        if (preg_match('/_(?:to|from|in|on)_(.+)_table$/', $name, $m)) {
            return $m[1];
        }

        return null;
    }

    private function createStub(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Database\\Capsule\\Manager as Capsule;

        return new class {
            public function up(): void
            {
                // Guarded so re-running against a hand-imported schema is a no-op
                // rather than an error — matches the bundled migrations.
                if (Capsule::schema()->hasTable('{$table}')) {
                    return;
                }

                Capsule::schema()->create('{$table}', function (\$table) {
                    \$table->id();

                    // Link rows to a Telegram user:
                    // \$table->unsignedBigInteger('user_id');
                    // \$table->foreign('user_id')->references('id')->on('telegram_users')->cascadeOnDelete();

                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Capsule::schema()->dropIfExists('{$table}');
            }
        };

        PHP;
    }

    private function alterStub(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Database\\Capsule\\Manager as Capsule;

        return new class {
            public function up(): void
            {
                Capsule::schema()->table('{$table}', function (\$table) {
                    // \$table->string('phone', 20)->nullable();
                });
            }

            public function down(): void
            {
                Capsule::schema()->table('{$table}', function (\$table) {
                    // \$table->dropColumn('phone');
                });
            }
        };

        PHP;
    }

    private function blankStub(): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Database\Capsule\Manager as Capsule;

        return new class {
            public function up(): void
            {
                // Capsule::schema()->create('my_table', function ($table) { ... });
            }

            public function down(): void
            {
                // Capsule::schema()->dropIfExists('my_table');
            }
        };

        PHP;
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }
}
