<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/devflow_lang_test_' . uniqid();
        mkdir($this->dir);

        file_put_contents($this->dir . '/en.php', <<<'PHP'
        <?php
        return [
            'welcome' => 'Hello, {name}!',
            'menu' => ['account' => 'My Account'],
        ];
        PHP);

        file_put_contents($this->dir . '/fa.php', <<<'PHP'
        <?php
        return [
            'welcome' => 'سلام، {name}!',
            'menu' => ['account' => 'حساب من'],
        ];
        PHP);

        Lang::setPath($this->dir);
        Lang::setDefaultLocale('en');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/en.php');
        @unlink($this->dir . '/fa.php');
        @rmdir($this->dir);
    }

    public function test_get_resolves_key_and_interpolates_vars(): void
    {
        $this->assertSame('Hello, Ali!', Lang::get('en', 'welcome', ['name' => 'Ali']));
        $this->assertSame('سلام، Ali!', Lang::get('fa', 'welcome', ['name' => 'Ali']));
    }

    public function test_get_supports_dot_notation(): void
    {
        $this->assertSame('My Account', Lang::get('en', 'menu.account'));
    }

    public function test_get_falls_back_to_default_locale_then_key(): void
    {
        $this->assertSame('Hello, {name}!', Lang::get('de', 'welcome'));
        $this->assertSame('missing.key', Lang::get('en', 'missing.key'));
    }

    public function test_find_key_reverse_lookup_across_locales(): void
    {
        $this->assertSame('menu.account', Lang::findKey('حساب من', ['en', 'fa']));
        $this->assertSame('menu.account', Lang::findKey('My Account', ['en', 'fa']));
        $this->assertNull(Lang::findKey('nonexistent', ['en', 'fa']));
    }

    public function test_has_reports_key_existence_per_locale(): void
    {
        $this->assertTrue(Lang::has('en', 'welcome'));
        $this->assertFalse(Lang::has('fa', 'missing.key'));
    }
}
