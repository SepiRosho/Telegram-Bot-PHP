<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\NewProjectCommand;
use PHPUnit\Framework\TestCase;

/**
 * The scaffold templates are plain strings inside NewProjectCommand, so they
 * are the one part of the library nothing else exercises — a broken template
 * only shows up when a user runs `devflow new`.
 */
class ScaffoldTemplateTest extends TestCase
{
    private function template(string $method, mixed ...$args): string
    {
        return (string) (new \ReflectionMethod(NewProjectCommand::class, $method))
            ->invoke(new NewProjectCommand(), ...$args);
    }

    /** @dataProvider phpTemplates */
    public function test_generated_php_templates_are_syntactically_valid(string $method): void
    {
        $file = tempnam(sys_get_temp_dir(), 'devflow_tpl_') . '.php';
        file_put_contents($file, $this->template($method));

        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
        unlink($file);

        $this->assertSame(0, $exit, "{$method} produced invalid PHP:\n" . implode("\n", $output));
    }

    public static function phpTemplates(): array
    {
        return array_map(fn(string $m) => [$m], [
            'bootstrapApp',
            'bootstrapHelpers',
            'publicWebhook',
            'helpCommand',
            'authMiddleware',
            'userHandlers',
            'adminHandlers',
            'userModel',
            'langEn',
            'langFa',
        ]);
    }

    // ─── Private-chat default ─────────────────────────────────────────────────

    public function test_bootstrap_restricts_the_bot_to_private_chats(): void
    {
        // Without this, a group /start writes a telegram_users row whose
        // chat_id is the group — which then receives every broadcast.
        $this->assertStringContainsString(
            "'allowed_chat_types' => ['private']",
            $this->template('bootstrapApp'),
        );
    }

    public function test_user_handlers_leave_a_group_the_bot_is_added_to(): void
    {
        $handlers = $this->template('userHandlers');

        $this->assertStringContainsString('Bot::onMyChatMember', $handlers);
        $this->assertStringContainsString('Bot::leaveChat', $handlers);
        $this->assertStringContainsString('userJoined()', $handlers);
    }

    public function test_lang_files_define_every_key_the_handlers_reference(): void
    {
        $en = eval('?>' . $this->template('langEn'));
        $fa = eval('?>' . $this->template('langFa'));

        $flatten = function (array $data, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($data as $key => $value) {
                $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                $keys = is_array($value) ? [...$keys, ...$flatten($value, $full)] : [...$keys, $full];
            }
            return $keys;
        };

        $enKeys = $flatten($en);
        sort($enKeys);
        $faKeys = $flatten($fa);
        sort($faKeys);

        // A key present in one locale but not the other silently falls back,
        // which reads as an untranslated bot rather than an error.
        $this->assertSame($enKeys, $faKeys, 'lang/en.php and lang/fa.php must define the same keys.');

        $source = $this->template('userHandlers') . $this->template('adminHandlers') . $this->template('helpCommand') . $this->template('authMiddleware');
        preg_match_all("/\\\$ctx->t\('([a-z_.]+)'/", $source, $matches);

        foreach (array_unique($matches[1]) as $used) {
            $this->assertContains($used, $enKeys, "Handler uses \$ctx->t('{$used}') but lang/en.php has no such key.");
        }
    }

    // ─── AI-facing files ──────────────────────────────────────────────────────

    public function test_generated_agents_md_carries_the_project_name(): void
    {
        $this->assertStringContainsString('# AGENTS.md — my-shop-bot', $this->template('agentsMd', 'my-shop-bot'));
    }

    public function test_generated_agents_md_points_at_the_library_reference(): void
    {
        $agents = $this->template('agentsMd', 'demo');

        $this->assertStringContainsString('vendor/devflow/telegram-bot/AGENTS.md', $agents);
        $this->assertStringContainsString('.ai/api.json', $agents);
    }

    public function test_generated_claude_md_defers_to_agents_md(): void
    {
        $this->assertStringContainsString('AGENTS.md', $this->template('claudeMd'));
    }

    public function test_this_library_ships_its_own_agents_md(): void
    {
        // The scaffold tells agents to read it at vendor/devflow/telegram-bot/AGENTS.md,
        // so it has to exist and not be export-ignored.
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root . '/AGENTS.md');
        $this->assertFileDoesNotExist(
            $root . '/.gitattributes',
            'A .gitattributes could export-ignore AGENTS.md out of the published package.',
        );
    }
}
