<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\Keyboard;
use PHPUnit\Framework\TestCase;

class KeyboardTest extends TestCase
{
    public function test_inline_returns_array_with_inline_keyboard_key(): void
    {
        $markup = Keyboard::inline([
            [Keyboard::button('OK', 'ok_pressed')],
        ]);

        $this->assertIsArray($markup);
        $this->assertArrayHasKey('inline_keyboard', $markup);
        $this->assertCount(1, $markup['inline_keyboard']);
        $this->assertSame('OK', $markup['inline_keyboard'][0][0]['text']);
        $this->assertSame('ok_pressed', $markup['inline_keyboard'][0][0]['callback_data']);
    }

    public function test_button_produces_callback_data_button(): void
    {
        $btn = Keyboard::button('Click me', 'action_1');

        $this->assertSame(['text' => 'Click me', 'callback_data' => 'action_1'], $btn);
    }

    public function test_url_button_produces_url_button(): void
    {
        $btn = Keyboard::url('Visit', 'https://example.com');

        $this->assertSame(['text' => 'Visit', 'url' => 'https://example.com'], $btn);
    }

    public function test_reply_keyboard_has_keyboard_key(): void
    {
        $markup = Keyboard::reply([['Button A', 'Button B']]);

        $this->assertArrayHasKey('keyboard', $markup);
        $this->assertTrue($markup['resize_keyboard']);
        $this->assertSame([[['text' => 'Button A'], ['text' => 'Button B']]], $markup['keyboard']);
    }

    public function test_reply_keyboard_one_time_flag(): void
    {
        $markup = Keyboard::reply([['Go']], resize: true, oneTime: true);

        $this->assertTrue($markup['one_time_keyboard']);
    }

    public function test_remove_returns_remove_keyboard(): void
    {
        $markup = Keyboard::remove();

        $this->assertSame(['remove_keyboard' => true], $markup);
    }

    public function test_inline_with_multiple_rows(): void
    {
        $markup = Keyboard::inline([
            [Keyboard::button('A', 'a'), Keyboard::button('B', 'b')],
            [Keyboard::button('C', 'c')],
        ]);

        $this->assertCount(2, $markup['inline_keyboard']);
        $this->assertCount(2, $markup['inline_keyboard'][0]);
        $this->assertCount(1, $markup['inline_keyboard'][1]);
    }
}
