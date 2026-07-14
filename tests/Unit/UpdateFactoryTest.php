<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Testing\UpdateFactory;
use PHPUnit\Framework\TestCase;

class UpdateFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        UpdateFactory::reset();
    }

    public function test_text_builds_a_plain_text_message(): void
    {
        $update = UpdateFactory::text('hello');

        $this->assertSame('hello', $update->message->text);
        $this->assertFalse($update->message->isCommand());
    }

    public function test_command_builds_a_recognized_command_with_args(): void
    {
        $update = UpdateFactory::command('start', ['ref123']);

        $this->assertTrue($update->message->isCommand());
        $this->assertSame('start', $update->message->command());
        $this->assertSame(['ref123'], $update->message->commandArgs());
    }

    public function test_callback_builds_a_callback_query(): void
    {
        $update = UpdateFactory::callback('btn_yes');

        $this->assertSame('btn_yes', $update->callbackQuery->data);
    }

    public function test_photo_builds_a_photo_message(): void
    {
        $update = UpdateFactory::photo();

        $this->assertNotNull($update->message->photo);
        $this->assertNull($update->message->text);
    }

    public function test_voice_builds_a_voice_message(): void
    {
        $update = UpdateFactory::voice();

        $this->assertNotNull($update->message->voice);
    }

    public function test_overrides_are_merged_into_the_default_payload(): void
    {
        $update = UpdateFactory::text('hi', ['message' => ['chat' => ['id' => 999, 'type' => 'private']]]);

        $this->assertSame(999, $update->message->chat->id);
        $this->assertSame('hi', $update->message->text);
    }
}
