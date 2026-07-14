<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Database\Models\BotSetting;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class BotUsernameTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('bot_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('bot_settings');
    }

    private function fakeHttpReturningUsername(string $username): FakeHttpClient
    {
        $http = new FakeHttpClient();
        $http->respond('getMe', ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => $username]);
        return $http;
    }

    public function test_username_is_persisted_to_bot_settings_when_database_enabled(): void
    {
        $http = $this->fakeHttpReturningUsername('my_cool_bot');
        $instance = new BotInstance('token', ['database' => true], $http);

        $this->assertSame('my_cool_bot', $instance->username());
        $this->assertSame(1, count($http->callsTo('getMe')));
        $this->assertSame('my_cool_bot', BotSetting::get('bot_username'));
    }

    public function test_username_reads_from_bot_settings_cache_without_calling_get_me_again(): void
    {
        BotSetting::set('bot_username', 'already_cached_bot');

        $http = $this->fakeHttpReturningUsername('should_not_be_used');
        $instance = new BotInstance('token', ['database' => true], $http);

        $this->assertSame('already_cached_bot', $instance->username());
        $this->assertSame(0, count($http->callsTo('getMe')));
    }

    public function test_username_memoizes_per_process_when_database_disabled(): void
    {
        $http = $this->fakeHttpReturningUsername('no_db_bot');
        $instance = new BotInstance('token', ['database' => false], $http);

        $this->assertSame('no_db_bot', $instance->username());
        $this->assertSame('no_db_bot', $instance->username());
        $this->assertSame(1, count($http->callsTo('getMe')));
    }
}
