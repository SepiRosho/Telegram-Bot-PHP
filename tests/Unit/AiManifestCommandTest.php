<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Console\Commands\AiManifestCommand;
use Devflow\TelegramBot\Context;
use PHPUnit\Framework\TestCase;

class AiManifestCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devflow_manifest_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'api.json';
        if (is_file($path)) {
            unlink($path);
            rmdir(dirname($path));
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    private function manifest(): array
    {
        (new AiManifestCommand())->writeTo($this->dir);

        return json_decode(
            (string) file_get_contents($this->dir . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'api.json'),
            true,
        );
    }

    public function test_it_writes_valid_json_to_the_given_root(): void
    {
        $this->assertIsArray($this->manifest());
    }

    public function test_version_matches_composer_json(): void
    {
        // A manifest claiming the wrong version is worse than none — this is
        // the same pair the release checklist keeps in sync.
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json'),
            true,
        );

        $this->assertSame($composer['version'], $this->manifest()['version']);
    }

    public function test_every_on_method_on_bot_instance_appears_in_routes(): void
    {
        $reflected = [];
        foreach ((new \ReflectionClass(BotInstance::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'on')) {
                $reflected[] = $method->getName();
            }
        }
        sort($reflected);

        $listed = array_column($this->manifest()['routes'], 'method');
        sort($listed);

        $this->assertSame($reflected, $listed);
    }

    public function test_router_types_are_extracted_from_the_method_bodies(): void
    {
        $byMethod = array_column($this->manifest()['routes'], 'router_type', 'method');

        $this->assertSame('command', $byMethod['onCommand']);
        $this->assertSame('text', $byMethod['onText']);
        $this->assertSame('callback_query', $byMethod['onCallbackQuery']);
        $this->assertSame('step', $byMethod['onStep']);
        $this->assertSame('my_chat_member', $byMethod['onMyChatMember']);
    }

    public function test_it_records_which_routes_lack_a_typed_facade_static(): void
    {
        $byMethod = array_column($this->manifest()['routes'], 'on_facade', 'method');

        $this->assertTrue($byMethod['onCommand'], 'onCommand has a typed static on Bot.');
        $this->assertFalse($byMethod['onBusinessConnection'], 'onBusinessConnection only works via __callStatic.');
    }

    public function test_context_methods_are_listed_with_signatures(): void
    {
        $byName = array_column($this->manifest()['context'], 'signature', 'name');

        $this->assertArrayHasKey('reply', $byName);
        $this->assertArrayHasKey('isPrivate', $byName);
        $this->assertSame('isPrivate(): bool', $byName['isPrivate']);
    }

    public function test_context_listing_matches_the_class(): void
    {
        $expected = [];
        foreach ((new \ReflectionClass(Context::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isConstructor() && !str_starts_with($method->getName(), '__')) {
                $expected[] = $method->getName();
            }
        }
        sort($expected);

        $listed = array_column($this->manifest()['context'], 'name');
        sort($listed);

        $this->assertSame($expected, $listed);
    }

    public function test_telegram_api_listing_includes_trait_methods(): void
    {
        $names = array_column($this->manifest()['telegram_api'], 'name');

        $this->assertContains('sendMessage', $names, 'Declared directly on TelegramApi.');
        $this->assertContains('createNewStickerSet', $names, 'Comes from the Stickers trait.');
        $this->assertContains('createForumTopic', $names, 'Comes from the ForumAndChatAdmin trait.');
        $this->assertGreaterThan(150, count($names));
    }

    public function test_every_documented_config_key_is_a_real_one(): void
    {
        // A manifest that invents config keys would send agents down a path
        // that silently does nothing, since unknown keys are just ignored.
        $documented = array_keys($this->manifest()['config']);

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Routing/Router.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/src/BotInstance.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/src/Database/UserRepository.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/src/Api/HttpClient.php');

        foreach ($documented as $key) {
            $this->assertStringContainsString($key, $source, "Config key [{$key}] is documented but never read.");
        }
    }

    public function test_cli_listing_matches_the_registered_commands(): void
    {
        $registered = array_keys(
            (new \ReflectionProperty(\Devflow\TelegramBot\Console\Application::class, 'commands'))
                ->getValue(new \Devflow\TelegramBot\Console\Application()),
        );

        // Manifest keys carry an argument hint ("new <name>"); compare the verb.
        $listed = array_map(
            fn(string $entry) => explode(' ', $entry)[0],
            array_keys($this->manifest()['cli']),
        );

        sort($registered);
        sort($listed);

        $this->assertSame($registered, $listed);
    }
}
