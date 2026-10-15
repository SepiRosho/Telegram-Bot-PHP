<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\AllowedUpdates;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class AllowedUpdatesTest extends TestCase
{
    /** @dataProvider unspecified */
    public function test_nothing_given_means_leave_telegrams_setting_alone(string|array|null $spec): void
    {
        $this->assertNull(AllowedUpdates::parse($spec));
    }

    public static function unspecified(): array
    {
        return ['null' => [null], 'empty string' => [''], 'blank' => ['  , '], 'empty array' => [[]]];
    }

    public function test_a_comma_list_is_trimmed_lowercased_and_deduplicated(): void
    {
        $this->assertSame(
            ['message', 'chat_member', 'callback_query'],
            AllowedUpdates::parse(' Message, chat_member ,message,callback_query'),
        );
    }

    public function test_an_array_works_like_a_list(): void
    {
        $this->assertSame(['message', 'poll'], AllowedUpdates::parse(['message', 'poll']));
    }

    public function test_default_is_the_empty_list_which_resets_telegram_to_its_default_set(): void
    {
        $this->assertSame([], AllowedUpdates::parse('default'));
    }

    public function test_all_includes_the_types_telegram_leaves_out_by_default(): void
    {
        $all = AllowedUpdates::parse('all');

        foreach (['chat_member', 'message_reaction', 'message_reaction_count'] as $type) {
            $this->assertContains($type, $all);
        }
    }

    public function test_all_can_be_mixed_with_names_without_duplicates(): void
    {
        $parsed = AllowedUpdates::parse('message,all');

        $this->assertSame($parsed, array_values(array_unique($parsed)));
        $this->assertCount(count(AllowedUpdates::ALL), $parsed);
    }

    public function test_an_unknown_type_is_rejected_and_the_valid_ones_listed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"mesage".*message, edited_message/');

        AllowedUpdates::parse('mesage');
    }

    public function test_every_type_the_library_can_route_is_listed(): void
    {
        // Update::type() is the router's source of truth for what an update is;
        // a type missing from ALL could never be requested by name.
        $source = file_get_contents((new \ReflectionClass(Update::class))->getFileName());
        preg_match_all("/=> '([a-z_]+)',/", $source, $m);

        $routable = array_diff(array_unique($m[1]), ['unknown']);

        $this->assertSame([], array_values(array_diff($routable, AllowedUpdates::ALL)));
    }
}
