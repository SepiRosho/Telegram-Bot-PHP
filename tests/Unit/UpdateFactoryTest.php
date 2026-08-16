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

    public function test_list_field_overrides_replace_rather_than_merge_by_index(): void
    {
        // array_replace_recursive can only overwrite indices present in the
        // override — it can never shrink or clear a list. An empty override
        // must actually remove the default bot_command entity.
        $update = UpdateFactory::command('start', overrides: ['message' => ['entities' => []]]);

        $this->assertSame([], $update->message->entities);
    }

    public function test_list_field_overrides_can_fully_swap_a_list(): void
    {
        $update = UpdateFactory::photo(['message' => ['photo' => [['file_id' => 'custom', 'width' => 1, 'height' => 1]]]]);

        $this->assertSame([['file_id' => 'custom', 'width' => 1, 'height' => 1]], $update->message->photo);
    }

    public function test_command_accepts_a_named_user_id(): void
    {
        $update = UpdateFactory::command('start', userId: 1);

        $this->assertSame(1, $update->message->from->id);
    }

    public function test_callback_query_is_an_alias_of_callback(): void
    {
        $update = UpdateFactory::callbackQuery('btn_yes');

        $this->assertSame('btn_yes', $update->callbackQuery->data);
    }
}
