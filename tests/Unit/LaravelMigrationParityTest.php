<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * The library ships its schema twice: as Laravel migrations
 * (src/Database/Migrations, published by `vendor:publish`) and as standalone
 * Capsule migrations (database/migrations, run by `devflow migrate`). Both
 * must describe the same tables, or a Laravel user and a standalone user end
 * up with different databases from the same package.
 */
class LaravelMigrationParityTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'standalone');
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'laravel');
        $this->capsule->setAsGlobal();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    private function runStandalone(): void
    {
        $this->capsule->getDatabaseManager()->setDefaultConnection('standalone');

        $files = glob(__DIR__ . '/../../database/migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            (require $file)->up();
        }
    }

    private function runLaravel(): void
    {
        $this->capsule->getDatabaseManager()->setDefaultConnection('laravel');

        // Laravel migrations talk to the Schema facade, which needs an app.
        $app = new Container();
        $app->instance('db.schema', $this->capsule->getConnection('laravel')->getSchemaBuilder());
        Facade::setFacadeApplication($app);

        foreach (['CreateTelegramUsersTable', 'CreateBotSettingsTable', 'CreateTelegramBroadcastsTable'] as $name) {
            (require __DIR__ . "/../../src/Database/Migrations/{$name}.php")->up();
        }
    }

    /** @return array<string, array{type: string, notnull: int, default: ?string}> */
    private function columns(string $connection, string $table): array
    {
        $rows = $this->capsule->getConnection($connection)->select("PRAGMA table_info('{$table}')");
        $out  = [];
        foreach ($rows as $r) {
            $out[$r->name] = ['type' => strtolower($r->type), 'notnull' => (int) $r->notnull, 'default' => $r->dflt_value];
        }
        ksort($out);

        return $out;
    }

    /** @return string[] */
    private function indexes(string $connection, string $table): array
    {
        $names = array_map(
            fn($r) => $r->name,
            $this->capsule->getConnection($connection)->select("PRAGMA index_list('{$table}')"),
        );
        sort($names);

        return $names;
    }

    /** @dataProvider tables */
    public function test_both_migration_sets_build_the_same_table(string $table): void
    {
        $this->runStandalone();
        $this->runLaravel();

        $this->assertNotEmpty($this->columns('laravel', $table), "{$table} was not created by the Laravel migrations.");
        $this->assertSame(
            $this->columns('standalone', $table),
            $this->columns('laravel', $table),
            "Columns of {$table} differ between database/migrations and src/Database/Migrations.",
        );
        $this->assertSame(
            $this->indexes('standalone', $table),
            $this->indexes('laravel', $table),
            "Indexes of {$table} differ between database/migrations and src/Database/Migrations.",
        );
    }

    public static function tables(): array
    {
        return [
            'telegram_users'      => ['telegram_users'],
            'bot_settings'        => ['bot_settings'],
            'telegram_broadcasts' => ['telegram_broadcasts'],
        ];
    }
}
