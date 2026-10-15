<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\InputFile;
use Devflow\TelegramBot\Support\AllowedUpdates;
use Devflow\TelegramBot\Support\WebhookOptions;
use PHPUnit\Framework\TestCase;

class WebhookOptionsTest extends TestCase
{
    public function test_nothing_given_sends_nothing(): void
    {
        $this->assertSame([], WebhookOptions::build([]));
        $this->assertSame([], WebhookOptions::build(['secret' => '', 'max_connections' => '', 'drop_pending' => false]));
    }

    public function test_every_option_maps_to_its_set_webhook_parameter(): void
    {
        $cert = tempnam(sys_get_temp_dir(), 'pem');

        try {
            $options = WebhookOptions::build([
                'secret'          => 'abc_DEF-123',
                'allowed_updates' => 'message,chat_member',
                'drop_pending'    => true,
                'max_connections' => '40',
                'ip_address'      => '203.0.113.7',
                'certificate'     => $cert,
            ]);
        } finally {
            unlink($cert);
        }

        $this->assertSame('abc_DEF-123', $options['secret_token']);
        $this->assertSame(['message', 'chat_member'], $options['allowed_updates']);
        $this->assertTrue($options['drop_pending_updates']);
        $this->assertSame(40, $options['max_connections']);
        $this->assertSame('203.0.113.7', $options['ip_address']);
        $this->assertInstanceOf(InputFile::class, $options['certificate']);
    }

    public function test_default_allowed_updates_stays_an_empty_list(): void
    {
        $this->assertSame([], WebhookOptions::build(['allowed_updates' => 'default'])['allowed_updates']);
    }

    /** @dataProvider badInput */
    public function test_bad_input_is_rejected_before_any_request(array $input, string $messagePart): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($messagePart, '/') . '/');

        WebhookOptions::build($input);
    }

    public static function badInput(): array
    {
        return [
            'secret with a space'    => [['secret' => 'has space'], 'secret'],
            'secret with a symbol'   => [['secret' => 'pa$$word'], 'secret'],
            'secret too long'        => [['secret' => str_repeat('a', 257)], 'secret'],
            'max connections zero'   => [['max_connections' => '0'], 'max_connections'],
            'max connections 101'    => [['max_connections' => '101'], 'max_connections'],
            'max connections text'   => [['max_connections' => 'lots'], 'max_connections'],
            'bad ip'                 => [['ip_address' => 'not-an-ip'], 'ip_address'],
            'missing certificate'    => [['certificate' => '/no/such/file.pem'], 'Certificate'],
            'unknown update type'    => [['allowed_updates' => 'mesage'], 'mesage'],
        ];
    }

    public function test_describe_summarises_what_will_be_sent(): void
    {
        $lines = WebhookOptions::describe(WebhookOptions::build([
            'allowed_updates' => 'message',
            'max_connections' => 10,
            'drop_pending'    => true,
            'secret'          => 'abc',
        ]));

        $this->assertContains('Allowed updates: message', $lines);
        $this->assertContains('Max connections: 10', $lines);
        $this->assertContains('Secret token: set', $lines);
        $this->assertContains('Dropping pending updates', $lines);
        $this->assertStringNotContainsString('abc', implode("\n", $lines), 'the secret itself must not be printed');
    }

    // ─── Route type → update type ─────────────────────────────────────────────

    public function test_required_updates_are_derived_from_route_types(): void
    {
        $needed = AllowedUpdates::requiredBy([
            'command', 'text', 'photo', 'step', 'callback_query', 'chat_member', 'message_reaction', 'update',
        ]);

        $this->assertEqualsCanonicalizing(['message', 'callback_query', 'chat_member', 'message_reaction'], $needed);
    }

    public function test_effective_resolves_the_default_set(): void
    {
        $default = AllowedUpdates::effective([]);

        $this->assertContains('message', $default);
        $this->assertNotContains('chat_member', $default);
        $this->assertNotContains('message_reaction', $default);
        $this->assertSame($default, AllowedUpdates::effective(null));
        $this->assertSame(['poll'], AllowedUpdates::effective(['poll']));
    }
}
