<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Database\UserRepository;
use Devflow\TelegramBot\Testing\UpdateFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();

        // See TelegramUserTest::resetGuardableColumnsCache() — other test
        // classes create a `telegram_users` table with a different column
        // set under this same model class, so Eloquent's per-class
        // guardable-columns cache must be cleared before each test.
        $ref = new \ReflectionClass(\Illuminate\Database\Eloquent\Model::class);
        $prop = $ref->getProperty('guardableColumns');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('telegram_users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->bigInteger('chat_id');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language_code', 10)->nullable();
            $table->string('role', 50)->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('telegram_users');
    }

    public function test_user_defaults_hook_seeds_extra_attributes_on_creation(): void
    {
        $repo = new UserRepository([
            'user_defaults' => fn($update) => ['role' => 'superadmin', 'joined_at' => '2026-01-01 00:00:00'],
        ]);

        $user = $repo->findOrCreateByUpdate(UpdateFactory::command('start'));

        $this->assertSame('superadmin', $user->role);
        $this->assertNotNull($user->joined_at);
    }

    public function test_user_defaults_hook_only_applies_on_first_creation(): void
    {
        $repo = new UserRepository([
            'user_defaults' => fn($update) => ['role' => 'superadmin'],
        ]);

        $first = $repo->findOrCreateByUpdate(UpdateFactory::command('start'));
        $first->role = 'user';
        $first->save();

        $second = $repo->findOrCreateByUpdate(UpdateFactory::text('hello'));

        $this->assertSame('user', $second->role);
    }

    public function test_works_without_user_defaults_configured(): void
    {
        $repo = new UserRepository();

        $user = $repo->findOrCreateByUpdate(UpdateFactory::command('start'));

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_language_code_is_refreshed_on_returning_users(): void
    {
        $repo = new UserRepository();

        $first = $repo->findOrCreateByUpdate(UpdateFactory::command('start', overrides: [
            'message' => ['from' => ['language_code' => 'en']],
        ]));
        $this->assertSame('en', $first->language_code);

        $second = $repo->findOrCreateByUpdate(UpdateFactory::text('hi', [
            'message' => ['from' => ['language_code' => 'fa']],
        ]));

        $this->assertSame('fa', $second->language_code);
        $this->assertSame('fa', $second->fresh()->language_code);
    }
}
