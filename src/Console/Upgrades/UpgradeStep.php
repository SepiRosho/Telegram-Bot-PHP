<?php

namespace Devflow\TelegramBot\Console\Upgrades;

/**
 * One versioned unit of `devflow upgrade`'s checklist. A version's changes to
 * what a scaffolded project needs get written once as a file under this
 * directory (named after the version, e.g. `1.15.0.php`) returning an array
 * of these — UpgradeCommand loads every file, in version order, and runs
 * them uniformly. See AGENTS.md's "Shipping a new version" note: adding a
 * file here is part of cutting a release, the same way bumping the version
 * constant is.
 *
 * Three shapes, matching what UpgradeCommand can safely do with a scaffolded
 * project:
 *
 * - fixable():  purely additive (a missing directory/file) — auto-applied,
 *               since there's nothing existing to overwrite.
 * - manual():   would mean editing a file the developer likely already
 *               customized (bootstrap/app.php) — only ever reported, with
 *               the exact snippet to paste in.
 * - note():     no scaffold change at all, just something worth telling a
 *               developer upgrading past this version (a new optional config
 *               key, a new CLI command) — always shown, never counted as a
 *               problem.
 */
final class UpgradeStep
{
    /**
     * @param callable(string $projectRoot): bool $check
     * @param (callable(string $projectRoot): void)|null $fix
     */
    private function __construct(
        private $check,
        public readonly string $okMessage,
        public readonly string $problemMessage,
        private $fix,
        public readonly ?string $manualSnippet,
    ) {
    }

    /** @param callable(string $projectRoot): bool $check */
    public static function fixable(callable $check, string $okMessage, string $problemMessage, callable $fix): self
    {
        return new self($check, $okMessage, $problemMessage, $fix, null);
    }

    /** @param callable(string $projectRoot): bool $check */
    public static function manual(callable $check, string $okMessage, string $problemMessage, string $snippet): self
    {
        return new self($check, $okMessage, $problemMessage, null, $snippet);
    }

    /** Always satisfied — informational only, never flagged as a problem. */
    public static function note(string $message): self
    {
        return new self(static fn() => true, $message, $message, null, null);
    }

    /**
     * A single named key present in both `.env` and `.env.example` (whichever
     * exist), appended with $line when missing from either. Covers the
     * common "new config toggle shipped, wire its env var" case in one line
     * per key instead of a hand-rolled check() every time.
     */
    public static function envKey(string $key, string $line): self
    {
        $files = ['.env', '.env.example'];

        $missingFrom = function (string $root) use ($key, $files): array {
            $missing = [];
            foreach ($files as $filename) {
                $path = $root . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($path) && !preg_match('/^' . preg_quote($key, '/') . '=/m', (string) file_get_contents($path))) {
                    $missing[] = $filename;
                }
            }
            return $missing;
        };

        return self::fixable(
            check: fn(string $root) => $missingFrom($root) === [],
            okMessage: ".env has {$key}",
            problemMessage: ".env is missing {$key}",
            fix: function (string $root) use ($missingFrom, $key, $line): void {
                foreach ($missingFrom($root) as $filename) {
                    $path     = $root . DIRECTORY_SEPARATOR . $filename;
                    $contents = (string) file_get_contents($path);
                    file_put_contents($path, rtrim($contents) . "\n{$line}\n");
                }
            },
        );
    }

    /**
     * A snippet that has to exist somewhere in bootstrap/app.php — matched on
     * a distinctive substring (usually the config key itself) rather than a
     * full-line diff, since whitespace/formatting will drift from the
     * scaffolded default the moment a developer touches the file.
     */
    public static function bootstrapMarker(string $marker, string $label, string $snippet): self
    {
        $satisfied = function (string $root) use ($marker): bool {
            $path = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
            return !file_exists($path) || str_contains((string) file_get_contents($path), $marker);
        };

        return self::manual(
            check: $satisfied,
            okMessage: "bootstrap/app.php already wires {$label}",
            problemMessage: "bootstrap/app.php is missing {$label}",
            snippet: $snippet,
        );
    }

    public function isSatisfied(string $projectRoot): bool
    {
        return ($this->check)($projectRoot);
    }

    public function isFixable(): bool
    {
        return $this->fix !== null;
    }

    public function isNote(): bool
    {
        return $this->fix === null && $this->manualSnippet === null;
    }

    public function apply(string $projectRoot): void
    {
        /** @var callable(string): void $fix */
        $fix = $this->fix;
        $fix($projectRoot);
    }
}
