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

    private function renderItem(): callable
    {
        return fn(string $item) => [Keyboard::button($item, "select_{$item}")];
    }

    public function test_paginate_returns_all_items_on_a_single_page(): void
    {
        $markup = Keyboard::paginate(['a', 'b', 'c'], 1, $this->renderItem(), 'list_', perPage: 10);

        // 3 item rows, no nav row since everything fits on one page.
        $this->assertCount(3, $markup['inline_keyboard']);
    }

    public function test_paginate_adds_nav_row_when_multiple_pages(): void
    {
        $markup = Keyboard::paginate(range(1, 25), 1, fn($n) => [Keyboard::button((string) $n, "n_{$n}")], 'list_', perPage: 10);

        // 10 item rows + 1 nav row.
        $this->assertCount(11, $markup['inline_keyboard']);
        $nav = end($markup['inline_keyboard']);
        $this->assertSame('1/3', $nav[0]['text']);
        $this->assertSame('list_page_2', $nav[1]['callback_data']);
    }

    public function test_paginate_middle_page_has_both_prev_and_next(): void
    {
        $markup = Keyboard::paginate(range(1, 25), 2, fn($n) => [Keyboard::button((string) $n, "n_{$n}")], 'list_', perPage: 10);

        $nav = end($markup['inline_keyboard']);
        $this->assertCount(3, $nav);
        $this->assertSame('list_page_1', $nav[0]['callback_data']);
        $this->assertSame('2/3', $nav[1]['text']);
        $this->assertSame('list_page_3', $nav[2]['callback_data']);
    }

    public function test_paginate_clamps_out_of_range_page(): void
    {
        $markup = Keyboard::paginate(range(1, 25), 99, fn($n) => [Keyboard::button((string) $n, "n_{$n}")], 'list_', perPage: 10);

        $nav = end($markup['inline_keyboard']);
        $this->assertSame('3/3', $nav[1]['text']);
    }
}
