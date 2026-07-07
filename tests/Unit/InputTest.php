<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Support\Input;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
    public function test_is_int_accepts_numeric_strings(): void
    {
        $this->assertTrue(Input::isInt('42'));
        $this->assertTrue(Input::isInt('-10'));
        $this->assertTrue(Input::isInt('0'));
    }

    public function test_is_int_rejects_floats_and_text(): void
    {
        $this->assertFalse(Input::isInt('3.14'));
        $this->assertFalse(Input::isInt('abc'));
        $this->assertFalse(Input::isInt(''));
    }

    public function test_is_float_accepts_float_strings(): void
    {
        $this->assertTrue(Input::isFloat('3.14'));
        $this->assertTrue(Input::isFloat('42'));
        $this->assertTrue(Input::isFloat('-0.5'));
    }

    public function test_is_float_rejects_text(): void
    {
        $this->assertFalse(Input::isFloat('abc'));
        $this->assertFalse(Input::isFloat(''));
    }

    public function test_is_email_validates_correctly(): void
    {
        $this->assertTrue(Input::isEmail('user@example.com'));
        $this->assertFalse(Input::isEmail('not-an-email'));
        $this->assertFalse(Input::isEmail(''));
    }

    public function test_is_url_validates_correctly(): void
    {
        $this->assertTrue(Input::isUrl('https://example.com'));
        $this->assertTrue(Input::isUrl('http://foo.bar/baz?q=1'));
        $this->assertFalse(Input::isUrl('not a url'));
    }

    public function test_to_int_converts_string(): void
    {
        $this->assertSame(42, Input::toInt('42'));
        $this->assertSame(-5, Input::toInt('-5'));
        $this->assertNull(Input::toInt('abc'));           // default is null
        $this->assertSame(0, Input::toInt('abc', 0));    // explicit default
    }

    public function test_clean_strips_tags_and_trims(): void
    {
        $result = Input::clean('  <b>Hello</b> world  ');
        $this->assertSame('Hello world', $result);
    }

    public function test_truncate_shortens_long_strings(): void
    {
        // truncate at 5: substr='Hello', no space → 'Hello…' (default suffix is unicode ellipsis)
        $result = Input::truncate('Hello world', 5);
        $this->assertSame('Hello…', $result);
    }

    public function test_truncate_breaks_at_last_space(): void
    {
        // 'Hello world' at length 8 → substr='Hello wo', last space at 5 → 'Hello…'
        $result = Input::truncate('Hello world', 8);
        $this->assertSame('Hello…', $result);
    }

    public function test_truncate_leaves_short_strings_unchanged(): void
    {
        $this->assertSame('Hi', Input::truncate('Hi', 10));
    }

    public function test_between_validates_range(): void
    {
        $this->assertTrue(Input::between(5, 1, 10));
        $this->assertTrue(Input::between(1, 1, 10));
        $this->assertTrue(Input::between(10, 1, 10));
        $this->assertFalse(Input::between(0, 1, 10));
        $this->assertFalse(Input::between(11, 1, 10));
    }

    public function test_min_length(): void
    {
        $this->assertTrue(Input::minLength('hello', 3));
        $this->assertFalse(Input::minLength('hi', 3));
    }

    public function test_max_length(): void
    {
        $this->assertTrue(Input::maxLength('hi', 5));
        $this->assertFalse(Input::maxLength('toolong', 5));
    }
}
