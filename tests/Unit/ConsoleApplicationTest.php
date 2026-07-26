<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Application;
use Devflow\TelegramBot\Console\Commands\WebhookCommand;
use PHPUnit\Framework\TestCase;

class ConsoleApplicationTest extends TestCase
{
    /** @return array<string, class-string> */
    private function registeredCommands(): array
    {
        $property = new \ReflectionProperty(Application::class, 'commands');

        return $property->getValue(new Application());
    }

    private function helpText(): string
    {
        $method = new \ReflectionMethod(Application::class, 'showHelp');

        ob_start();
        $method->invoke(new Application());

        return (string) ob_get_clean();
    }

    public function test_every_command_advertised_in_help_is_actually_registered(): void
    {
        // `devflow poll` used to point users at `webhook:delete`, which was
        // never implemented — running it just printed "Unknown command".
        $help = $this->helpText();
        $registered = array_keys($this->registeredCommands());

        preg_match_all('/\033\[32m([a-z]+(?::[a-z]+)?)\033\[0m/', $help, $matches);

        $advertised = array_unique(array_filter(
            $matches[1],
            fn(string $name) => $name !== 'devflow',
        ));

        $this->assertNotEmpty($advertised);
        $this->assertSame([], array_diff($advertised, $registered));
    }

    public function test_every_registered_command_class_exists_and_is_executable(): void
    {
        foreach ($this->registeredCommands() as $name => $class) {
            $this->assertTrue(class_exists($class), "Command [{$name}] maps to a missing class [{$class}].");
            $this->assertTrue(method_exists($class, 'execute'), "Command [{$name}] has no execute() method.");
        }
    }

    public function test_webhook_commands_share_one_class(): void
    {
        $registered = $this->registeredCommands();

        foreach (['webhook:set', 'webhook:delete', 'webhook:info'] as $name) {
            $this->assertSame(WebhookCommand::class, $registered[$name] ?? null);
        }
    }
}
