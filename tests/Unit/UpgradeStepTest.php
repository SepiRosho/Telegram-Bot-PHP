<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;
use PHPUnit\Framework\TestCase;

class UpgradeStepTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_upgradestep_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    // ─── fixable() ──────────────────────────────────────────────────────────

    public function test_fixable_reports_satisfied_state_via_the_check_callback(): void
    {
        $step = UpgradeStep::fixable(
            check: fn(string $root) => is_dir($root . '/x'),
            okMessage: 'x exists',
            problemMessage: 'x is missing',
            fix: fn(string $root) => mkdir($root . '/x'),
        );

        $this->assertFalse($step->isSatisfied($this->dir));
        $this->assertTrue($step->isFixable());
        $this->assertFalse($step->isNote());

        $step->apply($this->dir);

        $this->assertTrue($step->isSatisfied($this->dir));
    }

    // ─── manual() ───────────────────────────────────────────────────────────

    public function test_manual_is_never_fixable_and_carries_a_snippet(): void
    {
        $step = UpgradeStep::manual(
            check: fn() => false,
            okMessage: 'wired',
            problemMessage: 'not wired',
            snippet: "'key' => 'value',",
        );

        $this->assertFalse($step->isFixable());
        $this->assertFalse($step->isNote());
        $this->assertSame("'key' => 'value',", $step->manualSnippet);
    }

    // ─── note() ─────────────────────────────────────────────────────────────

    public function test_note_is_always_satisfied_and_never_fixable(): void
    {
        $step = UpgradeStep::note('Something new shipped.');

        $this->assertTrue($step->isSatisfied($this->dir));
        $this->assertTrue($step->isNote());
        $this->assertFalse($step->isFixable());
        $this->assertSame('Something new shipped.', $step->okMessage);
    }

    // ─── envKey() ───────────────────────────────────────────────────────────

    public function test_env_key_is_satisfied_when_the_files_do_not_exist(): void
    {
        $step = UpgradeStep::envKey('FOO', 'FOO=bar');

        $this->assertTrue($step->isSatisfied($this->dir));
    }

    public function test_env_key_is_satisfied_when_already_present(): void
    {
        file_put_contents($this->dir . '/.env', "BOT_TOKEN=abc\nFOO=bar\n");

        $step = UpgradeStep::envKey('FOO', 'FOO=bar');

        $this->assertTrue($step->isSatisfied($this->dir));
    }

    public function test_env_key_apply_appends_without_disturbing_existing_lines(): void
    {
        file_put_contents($this->dir . '/.env', "BOT_TOKEN=abc\n");
        file_put_contents($this->dir . '/.env.example', "BOT_TOKEN=\n");

        $step = UpgradeStep::envKey('FOO', 'FOO=bar');
        $this->assertFalse($step->isSatisfied($this->dir));

        $step->apply($this->dir);

        $this->assertSame("BOT_TOKEN=abc\nFOO=bar\n", (string) file_get_contents($this->dir . '/.env'));
        $this->assertSame("BOT_TOKEN=\nFOO=bar\n", (string) file_get_contents($this->dir . '/.env.example'));
        $this->assertTrue($step->isSatisfied($this->dir));
    }

    public function test_env_key_apply_only_touches_files_that_exist(): void
    {
        file_put_contents($this->dir . '/.env', "BOT_TOKEN=abc\n");
        // No .env.example in this project.

        $step = UpgradeStep::envKey('FOO', 'FOO=bar');
        $step->apply($this->dir);

        $this->assertFileDoesNotExist($this->dir . '/.env.example');
    }

    // ─── bootstrapMarker() ──────────────────────────────────────────────────

    public function test_bootstrap_marker_is_satisfied_when_bootstrap_is_missing(): void
    {
        $step = UpgradeStep::bootstrapMarker('some_marker', 'some_marker', "'some_marker' => true,");

        $this->assertTrue($step->isSatisfied($this->dir));
    }

    public function test_bootstrap_marker_reflects_whether_the_marker_string_is_present(): void
    {
        mkdir($this->dir . '/bootstrap');
        file_put_contents($this->dir . '/bootstrap/app.php', "<?php\nBot::init('t', ['other_key' => true]);\n");

        $missing = UpgradeStep::bootstrapMarker('some_marker', 'some_marker', "'some_marker' => true,");
        $this->assertFalse($missing->isSatisfied($this->dir));

        file_put_contents($this->dir . '/bootstrap/app.php', "<?php\nBot::init('t', ['some_marker' => true]);\n");
        $this->assertTrue($missing->isSatisfied($this->dir));
    }
}
