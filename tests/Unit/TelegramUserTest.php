<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Database\Models\TelegramUser;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class TelegramUserTest extends TestCase
{
    protected function setUp(): void
    {
        // Eloquent caches which columns are "guardable" per model class name
        // (Model::$guardableColumns), introspected from whatever connection
        // was active the first time it's checked. Other test classes create
        // a `telegram_users` table with a different column set under this
        // same model class, so that cache must be cleared here or a stale
        // list from a previous test silently drops columns in this one.
        self::resetGuardableColumnsCache();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('telegram_users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->unsignedBigInteger('chat_id');
            $table->string('first_name');
            $table->string('anon_token')->nullable();
            $table->boolean('custom_flag')->default(false);
        });
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('telegram_users');
    }

    private static function resetGuardableColumnsCache(): void
    {
        $ref = new \ReflectionClass(\Illuminate\Database\Eloquent\Model::class);
        $prop = $ref->getProperty('guardableColumns');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    public function test_columns_present_in_the_table_are_mass_assignable_by_default(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 1,
            'chat_id'     => 100,
            'first_name'  => 'Ali',
            'anon_token'  => 'abc123',
            'custom_flag' => true,
        ]);

        $this->assertSame('abc123', $user->fresh()->anon_token);
        $this->assertTrue((bool) $user->fresh()->custom_flag);
    }

    public function test_only_id_is_explicitly_guarded(): void
    {
        $user = new TelegramUser();

        $this->assertSame(['id'], $user->getGuarded());
        $this->assertFalse($user->isFillable('id'));
    }
}
