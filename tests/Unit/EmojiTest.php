<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\Emoji;
use PHPUnit\Framework\TestCase;

class EmojiTest extends TestCase
{
    protected function setUp(): void
    {
        Emoji::reset();
        Emoji::register('fire', '5368324170671202286', '🔥');
    }

    public function test_get_returns_html_markup_by_default(): void
    {
        $this->assertSame(
            '<tg-emoji emoji-id="5368324170671202286">🔥</tg-emoji>',
            Emoji::get('fire'),
        );
    }

    public function test_get_returns_markdownv2_markup(): void
    {
        $this->assertSame(
            '![🔥](tg://emoji?id=5368324170671202286)',
            Emoji::get('fire', 'MarkdownV2'),
        );
    }

    public function test_parse_mode_is_case_insensitive(): void
    {
        $this->assertSame(Emoji::get('fire', 'HTML'), Emoji::get('fire', 'html'));
        $this->assertSame(Emoji::get('fire', 'MarkdownV2'), Emoji::get('fire', 'markdownv2'));
    }

    public function test_legacy_markdown_parse_mode_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Emoji::get('fire', 'Markdown');
    }

    public function test_get_leaves_an_unregistered_name_as_the_literal_shortcode(): void
    {
        $this->assertSame(':unknown:', Emoji::get('unknown'));
    }

    public function test_has_reports_registration_state(): void
    {
        $this->assertTrue(Emoji::has('fire'));
        $this->assertFalse(Emoji::has('unknown'));
    }

    public function test_text_expands_every_registered_shortcode_in_a_string(): void
    {
        Emoji::register('star', '5368324170671202287', '⭐');

        $result = Emoji::text('Nice :fire: work, you earned a :star: today!');

        $this->assertSame(
            'Nice <tg-emoji emoji-id="5368324170671202286">🔥</tg-emoji> work, you earned a '
                . '<tg-emoji emoji-id="5368324170671202287">⭐</tg-emoji> today!',
            $result,
        );
    }

    public function test_text_leaves_unregistered_shortcodes_untouched(): void
    {
        $this->assertSame('See you at 12:30:00 sharp.', Emoji::text('See you at 12:30:00 sharp.'));
    }

    public function test_text_supports_markdownv2(): void
    {
        $this->assertSame(
            'Nice ![🔥](tg://emoji?id=5368324170671202286) work!',
            Emoji::text('Nice :fire: work!', 'MarkdownV2'),
        );
    }

    public function test_register_many_accepts_the_associative_form(): void
    {
        Emoji::reset();
        Emoji::registerMany(['fire' => ['id' => '111', 'fallback' => '🔥']]);

        $this->assertSame('<tg-emoji emoji-id="111">🔥</tg-emoji>', Emoji::get('fire'));
    }

    public function test_register_many_accepts_the_positional_form(): void
    {
        Emoji::reset();
        Emoji::registerMany(['fire' => ['111', '🔥']]);

        $this->assertSame('<tg-emoji emoji-id="111">🔥</tg-emoji>', Emoji::get('fire'));
    }
}
