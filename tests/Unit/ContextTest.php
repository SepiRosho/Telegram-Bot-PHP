<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/devflow_ctx_lang_test_' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/en.php', "<?php\nreturn ['hi' => 'Hi, {name}!'];");
        file_put_contents($this->dir . '/fa.php', "<?php\nreturn ['hi' => 'سلام {name}!'];");
        Lang::setPath($this->dir);
        Lang::setDefaultLocale('en');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/en.php');
        @unlink($this->dir . '/fa.php');
        @rmdir($this->dir);
    }

    private function makeApi(): TelegramApi
    {
        return new TelegramApi($this->createMock(HttpClientInterface::class));
    }

    private function fakeDbUser(?string $language = null): object
    {
        return new class($language) {
            public function __construct(public ?string $language) {}
            public function save(): void {}
        };
    }

    private function update(string $clientLang = 'fa'): Update
    {
        return Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali', 'language_code' => $clientLang],
                'text'       => 'hi',
            ],
        ]);
    }

    public function test_locale_prefers_stored_preference_over_client_language(): void
    {
        $ctx = new Context($this->update('fa'), $this->makeApi());
        $repo = new class($this->fakeDbUser('en')) {
            public function __construct(private object $user) {}
            public function findOrCreateByUpdate($update): object { return $this->user; }
        };
        $ctx->setUserRepository($repo);

        $this->assertSame('en', $ctx->locale());
        $this->assertSame('Hi, Ali!', $ctx->t('hi', ['name' => 'Ali']));
    }

    public function test_locale_falls_back_to_client_language_when_no_stored_preference(): void
    {
        $ctx = new Context($this->update('fa'), $this->makeApi());
        $repo = new class($this->fakeDbUser(null)) {
            public function __construct(private object $user) {}
            public function findOrCreateByUpdate($update): object { return $this->user; }
        };
        $ctx->setUserRepository($repo);

        $this->assertSame('fa', $ctx->locale());
        $this->assertSame('سلام Ali!', $ctx->t('hi', ['name' => 'Ali']));
    }

    public function test_set_locale_persists_to_db_user(): void
    {
        $user = $this->fakeDbUser(null);
        $ctx = new Context($this->update('fa'), $this->makeApi());
        $repo = new class($user) {
            public function __construct(private object $user) {}
            public function findOrCreateByUpdate($update): object { return $this->user; }
        };
        $ctx->setUserRepository($repo);

        $ctx->setLocale('en');

        $this->assertSame('en', $ctx->locale());
    }

    // -------------------------------------------------------------------------
    // Business API / guest message resolution — these routes fire correctly
    // but used to leave $ctx->message()/text()/from()/chatId() all null-ish
    // because Context::message() never looked past the ordinary message types.
    // -------------------------------------------------------------------------

    public static function businessUpdateProvider(): array
    {
        return [
            'business_message'         => ['business_message'],
            'edited_business_message'  => ['edited_business_message'],
            'guest_message'            => ['guest_message'],
        ];
    }

    /** @dataProvider businessUpdateProvider */
    public function test_context_resolves_business_api_message_types(string $field): void
    {
        $update = Update::fromArray([
            'update_id' => 1,
            $field      => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 555, 'type' => 'private'],
                'from'       => ['id' => 777, 'is_bot' => false, 'first_name' => 'Biz'],
                'text'       => 'hello',
            ],
        ]);

        $ctx = new Context($update, $this->makeApi());

        $this->assertSame('hello', $ctx->text());
        $this->assertSame(555, $ctx->chatId());
        $this->assertSame(777, $ctx->userId());
        $this->assertNotNull($ctx->from());
    }
}
