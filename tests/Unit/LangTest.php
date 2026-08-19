<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\Lang;
use Devflow\TelegramBot\Support\Log;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        Lang::setAutoFallback(false);
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

    // -------------------------------------------------------------------------
    // BCP-47 / IETF tag normalization
    // -------------------------------------------------------------------------

    public function test_region_tagged_locale_resolves_to_its_base_language(): void
    {
        // A real Telegram client can report fa-IR where the project only ships
        // fa.php — this used to fall straight through to English with no error.
        $this->assertSame('سلام، علی!', Lang::get('fa-IR', 'welcome', ['name' => 'علی']));
    }

    public function test_normalization_is_case_insensitive_and_accepts_underscores(): void
    {
        $this->assertSame('حساب من', Lang::get('FA_ir', 'menu.account'));
    }

    public function test_subtags_are_dropped_one_at_a_time(): void
    {
        file_put_contents($this->dir . '/zh-hans.php', "<?php\nreturn ['welcome' => '你好，{name}！'];\n");
        Lang::setPath($this->dir);

        $this->assertSame('你好，Ali！', Lang::get('zh-Hans-CN', 'welcome', ['name' => 'Ali']));

        @unlink($this->dir . '/zh-hans.php');
        Lang::setPath($this->dir);
    }

    public function test_an_exact_regional_file_wins_over_its_base_language(): void
    {
        file_put_contents($this->dir . '/pt-br.php', "<?php\nreturn ['welcome' => 'Olá, {name}!'];\n");
        Lang::setPath($this->dir);

        $this->assertSame('Olá, Ana!', Lang::get('pt-BR', 'welcome', ['name' => 'Ana']));

        @unlink($this->dir . '/pt-br.php');
        Lang::setPath($this->dir);
    }

    public function test_unknown_locale_still_falls_back_to_the_default(): void
    {
        $this->assertSame('Hello, Ali!', Lang::get('de-AT', 'welcome', ['name' => 'Ali']));
    }

    public function test_normalize_returns_the_most_specific_resolvable_tag(): void
    {
        $this->assertSame('fa', Lang::normalize('fa-IR'));
        $this->assertSame('en', Lang::normalize('en'));
    }

    public function test_normalize_falls_back_to_the_bare_primary_subtag(): void
    {
        $this->assertSame('de', Lang::normalize('de-AT'));
    }

    public function test_find_key_matches_a_region_tagged_locale(): void
    {
        $this->assertSame('menu.account', Lang::findKey('حساب من', ['fa-IR']));
    }

    // -------------------------------------------------------------------------
    // Auto-fallback across every shipped lang file
    // -------------------------------------------------------------------------

    public function test_auto_fallback_off_by_default_returns_the_raw_key(): void
    {
        file_put_contents($this->dir . '/de.php', "<?php\nreturn ['only_in_de' => 'Nur auf Deutsch'];\n");
        Lang::setPath($this->dir);

        $this->assertSame('only_in_de', Lang::get('en', 'only_in_de'));

        @unlink($this->dir . '/de.php');
    }

    public function test_auto_fallback_finds_the_key_in_another_lang_file(): void
    {
        file_put_contents($this->dir . '/de.php', "<?php\nreturn ['only_in_de' => 'Nur auf Deutsch'];\n");
        Lang::setPath($this->dir);
        Lang::setAutoFallback(true);

        $this->assertSame('Nur auf Deutsch', Lang::get('en', 'only_in_de'));

        @unlink($this->dir . '/de.php');
    }

    public function test_auto_fallback_still_prefers_the_requested_locale_and_default_locale(): void
    {
        Lang::setAutoFallback(true);

        $this->assertSame('Hello, Ali!', Lang::get('en', 'welcome', ['name' => 'Ali']));
        $this->assertSame('سلام، Ali!', Lang::get('fa', 'welcome', ['name' => 'Ali']));
    }

    public function test_auto_fallback_still_returns_the_raw_key_when_nothing_has_it(): void
    {
        Lang::setAutoFallback(true);

        $this->assertSame('missing.everywhere', Lang::get('en', 'missing.everywhere'));
    }

    public function test_auto_fallback_logs_a_warning_when_it_fires(): void
    {
        $logDir = sys_get_temp_dir() . '/devflow_lang_log_' . uniqid();
        mkdir($logDir);
        Log::setPath($logDir);

        file_put_contents($this->dir . '/de.php', "<?php\nreturn ['only_in_de' => 'Nur auf Deutsch'];\n");
        Lang::setPath($this->dir);
        Lang::setAutoFallback(true);

        Lang::get('en', 'only_in_de');

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);
        $contents = (string) file_get_contents($logFile);
        $this->assertStringContainsString('[WARNING]', $contents);
        $this->assertStringContainsString('only_in_de', $contents);
        $this->assertStringContainsString('de', $contents);

        @unlink($this->dir . '/de.php');
        @unlink($logFile);
        @rmdir($logDir);
        Log::setPath('');
    }
}
