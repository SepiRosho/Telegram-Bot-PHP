<?php

namespace Devflow\TelegramBot\Testing;

use Devflow\TelegramBot\Exceptions\AssertionFailedException;

/**
 * FakeBot ships under src/ (production autoload) but its assertions used to
 * call PHPUnit\Framework\Assert directly — and phpunit/phpunit is a
 * require-dev dependency, which Composer never installs into a consuming
 * project. The documented Bot::fake() workflow therefore fataled with a bare
 * "Class PHPUnit\Framework\Assert not found" on the very first assertion.
 *
 * Delegate to PHPUnit when it is installed, so assertion counts and failure
 * output stay native to the test run; fall back to a plain exception when it
 * isn't, so Bot::fake() is usable from any test framework — or from a
 * throwaway script with no test framework at all.
 */
final class Assertions
{
    public static function assert(bool $passed, string $message): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::assertTrue($passed, $message);
            return;
        }

        if (!$passed) {
            throw new AssertionFailedException($message);
        }
    }
}
