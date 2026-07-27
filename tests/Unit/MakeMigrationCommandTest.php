<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\MakeMigrationCommand;
use PHPUnit\Framework\TestCase;

class MakeMigrationCommandTest extends TestCase
{
    private string $dir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_mig_' . uniqid();
        mkdir($this->dir, 0755, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        $migrations = $this->dir . '/database/migrations';
        foreach (glob($migrations . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        foreach ([$migrations, $this->dir . '/database', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    private function make(string $name): string
    {
        ob_start();
        (new MakeMigrationCommand())->execute([$name]);

        return (string) ob_get_clean();
    }

    private function generatedFiles(): array
    {
        return glob($this->dir . '/database/migrations/*.php') ?: [];
    }

    public function test_it_creates_the_migrations_directory_when_missing(): void
    {
        $this->make('create_orders_table');

        $this->assertDirectoryExists($this->dir . '/database/migrations');
        $this->assertCount(1, $this->generatedFiles());
    }

    public function test_the_filename_carries_a_sortable_timestamp_prefix(): void
    {
        $this->make('create_orders_table');

        // Ordering across the bundled and project migrations depends entirely
        // on this prefix format.
        $this->assertMatchesRegularExpression(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_create_orders_table\.php$/',
            basename($this->generatedFiles()[0]),
        );
    }

    public function test_a_create_migration_targets_the_table_from_its_name(): void
    {
        $this->make('create_orders_table');
        $body = (string) file_get_contents($this->generatedFiles()[0]);

        $this->assertStringContainsString("Capsule::schema()->create('orders'", $body);
        $this->assertStringContainsString("Capsule::schema()->dropIfExists('orders')", $body);
        $this->assertStringContainsString("hasTable('orders')", $body);
    }

    public function test_an_alter_migration_targets_the_table_after_to(): void
    {
        $this->make('add_phone_to_telegram_users_table');
        $body = (string) file_get_contents($this->generatedFiles()[0]);

        $this->assertStringContainsString("Capsule::schema()->table('telegram_users'", $body);
        $this->assertStringNotContainsString('->create(', $body);
    }

    public function test_an_unrecognised_name_still_produces_a_valid_blank_migration(): void
    {
        $this->make('backfill_legacy_data');
        $body = (string) file_get_contents($this->generatedFiles()[0]);

        $this->assertStringContainsString('public function up(): void', $body);
        $this->assertStringContainsString('public function down(): void', $body);
    }

    public function test_generated_migrations_are_syntactically_valid_php(): void
    {
        foreach (['create_orders_table', 'add_phone_to_telegram_users_table', 'backfill_legacy_data'] as $name) {
            $this->make($name);
        }

        foreach ($this->generatedFiles() as $file) {
            $migration = require $file;

            $this->assertIsObject($migration);
            $this->assertTrue(method_exists($migration, 'up'));
            $this->assertTrue(method_exists($migration, 'down'));
        }
    }

    /**
     * Tested through reflection rather than execute(), which exits — and an
     * exit() inside a test takes the whole PHPUnit process with it.
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(MakeMigrationCommand::class, $method))
            ->invoke(new MakeMigrationCommand(), ...$args);
    }

    /** @dataProvider migrationNames */
    public function test_name_validation(string $name, bool $valid): void
    {
        $this->assertSame($valid, $this->invokePrivate('isValidName', $name));
    }

    public static function migrationNames(): array
    {
        return [
            'snake_case'            => ['create_orders_table', true],
            'single word'           => ['orders', true],
            'digits allowed'        => ['create_v2_orders_table', true],
            'PascalCase rejected'   => ['CreateOrdersTable', false],
            'leading digit'         => ['2_orders', false],
            'spaces rejected'       => ['create orders', false],
            'hyphens rejected'      => ['create-orders', false],
            'empty rejected'        => ['', false],
        ];
    }

    /** @dataProvider tableNames */
    public function test_table_is_guessed_from_the_migration_name(string $name, ?string $expected): void
    {
        $this->assertSame($expected, $this->invokePrivate('guessTable', $name));
    }

    public static function tableNames(): array
    {
        return [
            ['create_orders_table', 'orders'],
            ['create_order_items_table', 'order_items'],
            ['add_phone_to_telegram_users_table', 'telegram_users'],
            ['remove_legacy_from_orders_table', 'orders'],
            ['drop_index_on_orders_table', 'orders'],
            ['backfill_legacy_data', null],
        ];
    }
}
