<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\BotInstance;
use Devflow\TelegramBot\Context;
use Devflow\TelegramBot\Support\Input;
use Devflow\TelegramBot\Support\Keyboard;
use Devflow\TelegramBot\Support\Lang;

/**
 * Writes .ai/api.json — a machine-readable index of the whole library surface,
 * for coding agents to grep instead of reading src/ and docs/.
 *
 * Signatures come from reflection rather than a hand-written list, so the
 * manifest cannot drift from the code the way prose documentation does. Only
 * the rules/config blocks below are curated, because intent isn't reflectable.
 */
class AiManifestCommand
{
    public function execute(array $args): void
    {
        if (in_array('--stdout', $args, true)) {
            echo $this->json() . "\n";
            return;
        }

        $counts = $this->writeTo((string) getcwd());

        if ($counts === null) {
            exit(1);
        }

        $this->success("Wrote .ai/api.json  ({$counts})");
        $this->line('Point your coding agent at this file instead of having it read src/.');
    }

    /**
     * Writes .ai/api.json under $root. Also called by `devflow new`, so a
     * freshly scaffolded project has the manifest before an agent ever looks
     * for it.
     *
     * @return ?string A short summary of what was written, or null on failure.
     */
    public function writeTo(string $root): ?string
    {
        $manifest = $this->build();
        $dir      = $root . DIRECTORY_SEPARATOR . '.ai';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->error("Could not create directory: {$dir}");
            return null;
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'api.json', $json . "\n");

        return sprintf(
            '%d routes, %d context methods, %d API methods',
            count($manifest['routes']),
            count($manifest['context']),
            count($manifest['telegram_api']),
        );
    }

    private function json(): string
    {
        return (string) json_encode(
            $this->build(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function build(): array
    {
        return [
            'library'      => 'devflow/telegram-bot',
            'version'      => $this->version(),
            'generated_at' => date('c'),
            'generator'    => 'vendor/bin/devflow ai:manifest',
            'rules'        => $this->rules(),
            'config'       => $this->config(),
            'routes'       => $this->routes(),
            'context'      => $this->methodsOf(Context::class),
            'bot_facade'   => $this->methodsOf(Bot::class),
            'telegram_api' => $this->methodsOf(TelegramApi::class),
            'support'      => [
                'Keyboard' => $this->methodsOf(Keyboard::class),
                'Lang'     => $this->methodsOf(Lang::class),
                'Input'    => $this->methodsOf(Input::class),
            ],
            'cli' => $this->cli(),
        ];
    }

    /**
     * The mistakes that cost an agent a debugging round-trip because the
     * failure is silent rather than an exception.
     */
    private function rules(): array
    {
        return [
            'Every $options array (including reply_markup) must be a PHP array. Never call json_encode() yourself — HttpClient encodes it, and pre-encoding produces a double-encoded value Telegram silently ignores.',
            'Keyboard::inline()/reply()/button()/url() already return arrays. Pass their result straight to reply_markup.',
            'Step routes are evaluated before all other route types by default (step_routes_first), so a broad onText() cannot swallow a mid-flow message.',
            'onStep() requires database => true. Without it, $ctx->step() is always null and no step route matches.',
            'Set allowed_chat_types => [\'private\'] unless the bot is meant to work in groups. Without it a group /start registers a telegram_users row whose chat_id is the group, which corrupts broadcasts.',
            'Bot::use() accepts a class name, a closure, or a constructed MiddlewareInterface instance.',
            'Use InputFile::path() / InputFile::contents() to send a local file. A plain string is treated as a file_id or a URL.',
            'Handler groups are plain classes with a static register() method, listed in Bot::loadHandlers([...]).',
            'Bot::fake() swaps the static instance for an in-memory one; production register() code runs against it unmodified.',
        ];
    }

    private function config(): array
    {
        return [
            'database'             => ['type' => 'bool', 'default' => false, 'description' => 'Enables DB user auto-registration, $ctx->user() and flow state. Required by onStep().'],
            'webhook_secret'       => ['type' => '?string', 'default' => null, 'description' => 'Validates the X-Telegram-Bot-Api-Secret-Token header. Must match setWebhook\'s secret_token.'],
            'proxy'                => ['type' => '?string', 'default' => null, 'description' => 'HTTP or SOCKS5 proxy URL, e.g. socks5://host:1080.'],
            'lang_path'            => ['type' => '?string', 'default' => null, 'description' => 'Directory of per-locale {locale}.php files for Support\\Lang.'],
            'default_locale'       => ['type' => 'string', 'default' => 'en', 'description' => 'Fallback locale when no user preference or client language resolves.'],
            'lang_auto_fallback'   => ['type' => 'bool', 'default' => false, 'description' => 'When a $ctx->t() key is missing from both the resolved locale and default_locale, search every other lang file before falling back to the raw key. Logs a WARNING when it fires.'],
            'language_code'        => ['type' => 'string', 'default' => 'auto', 'description' => "'auto' records each user's own Telegram language. Any other value forces every user's language_code and language columns to it."],
            'user_model'           => ['type' => 'class-string', 'default' => 'Devflow\\TelegramBot\\Database\\Models\\TelegramUser', 'description' => 'Custom Eloquent model for UserRepository.'],
            'user_defaults'        => ['type' => '?callable', 'default' => null, 'description' => 'callable(Update): array — extra attributes merged in when a user row is first created.'],
            'debug'                => ['type' => 'bool', 'default' => false, 'description' => 'Logs route match/no-match decisions on every dispatch, including chat-type filter drops.'],
            'step_routes_first'    => ['type' => 'bool', 'default' => true, 'description' => 'Evaluate all step routes before other route types, independent of registration order.'],
            'auto_answer_callbacks' => ['type' => 'bool', 'default' => true, 'description' => 'Auto-calls answerCallbackQuery when no route matched a callback query, clearing the tap spinner.'],
            'allowed_chat_types'   => ['type' => '?array', 'default' => null, 'description' => "Restricts routes to these chat types, e.g. ['private']. null means no filtering. Updates with no chat, and group/channel-only update types like my_chat_member, always pass."],
            'max_retries'          => ['type' => 'int', 'default' => 2, 'description' => 'How many times to retry after a 429 (or, with retry_transient, a 5xx/network failure), honouring Telegram\'s retry_after. Uploads are retried too — a fresh stream is opened per attempt.'],
            'max_retry_after'      => ['type' => 'int', 'default' => 60, 'description' => 'Longest wait (seconds) worth sleeping through before throwing instead.'],
            'retry_transient'      => ['type' => 'bool', 'default' => false, 'description' => 'Also retry isTransient() errors (5xx, network failures) using exponential backoff, not just a 429.'],
            'retry_jitter'         => ['type' => 'float', 'default' => 0.0, 'description' => 'Extra random jitter added on top of the computed wait, as a fraction of it (0.1 = up to +10%).'],
            'retry_strategy'       => ['type' => '?callable', 'default' => null, 'description' => 'callable(int $attempt, int $baseWaitSeconds, TelegramApiException $e): int — overrides the computed wait entirely.'],
            'on_retry'             => ['type' => '?callable', 'default' => null, 'description' => 'callable(int $attempt, int $waitSeconds, string $method, TelegramApiException $e): void — observer invoked before sleeping.'],
            'sleeper'              => ['type' => '?callable', 'default' => null, 'description' => 'callable(int $seconds): void — replaces the blocking sleep(), e.g. to suspend a Fiber instead.'],
            'timeout'              => ['type' => 'int', 'default' => 30, 'description' => 'HTTP timeout in seconds.'],
        ];
    }

    /** Maps each on*() registration method to the router type it produces. */
    private function routes(): array
    {
        $out = [];

        foreach ((new \ReflectionClass(BotInstance::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(), 'on')) {
                continue;
            }

            $out[] = [
                'method'      => $method->getName(),
                'router_type' => $this->routerTypeOf($method),
                'signature'   => $this->signature($method),
                'on_facade'   => method_exists(Bot::class, $method->getName()),
            ];
        }

        usort($out, fn(array $a, array $b) => $a['method'] <=> $b['method']);

        return $out;
    }

    /**
     * Reads the literal first argument of the addRoute() call in the method
     * body. Cheaper and more accurate than maintaining a parallel lookup table
     * that would silently rot the next time a route type is added.
     */
    private function routerTypeOf(\ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();
        if ($file === false) {
            return null;
        }

        $lines = @file($file);
        if ($lines === false) {
            return null;
        }

        $body = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        return preg_match("/addRoute\(\s*'([a-z_]+)'/", $body, $m) ? $m[1] : null;
    }

    /** @return list<array{name: string, signature: string}> */
    private function methodsOf(string $class): array
    {
        $out = [];

        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Only the class's own surface: inherited framework noise is not
            // part of what an agent is being told to call.
            if ($method->getDeclaringClass()->getName() !== $class && !$this->isTraitMethodOf($method, $class)) {
                continue;
            }

            $out[] = [
                'name'      => $method->getName(),
                'signature' => $this->signature($method),
            ];
        }

        usort($out, fn(array $a, array $b) => $a['name'] <=> $b['name']);

        return $out;
    }

    /** TelegramApi splits ~160 methods across traits; those still count as its own surface. */
    private function isTraitMethodOf(\ReflectionMethod $method, string $class): bool
    {
        foreach ((new \ReflectionClass($class))->getTraitNames() as $trait) {
            if (method_exists($trait, $method->getName())) {
                return true;
            }
        }

        return false;
    }

    private function signature(\ReflectionMethod $method): string
    {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->hasType() ? $this->shortType((string) $param->getType()) . ' ' : '';
            $part = $type . '$' . $param->getName();

            if ($param->isDefaultValueAvailable()) {
                $part .= ' = ' . $this->defaultValue($param);
            }

            $params[] = $part;
        }

        $returns = $method->hasReturnType() ? ': ' . $this->shortType((string) $method->getReturnType()) : '';

        return $method->getName() . '(' . implode(', ', $params) . ')' . $returns;
    }

    private function defaultValue(\ReflectionParameter $param): string
    {
        try {
            $value = $param->getDefaultValue();
        } catch (\Throwable) {
            return '?';
        }

        return match (true) {
            $value === null  => 'null',
            $value === []    => '[]',
            is_bool($value)  => $value ? 'true' : 'false',
            is_string($value) => "'" . $value . "'",
            is_array($value) => (string) json_encode($value),
            default          => (string) $value,
        };
    }

    /** Strips namespaces so signatures stay readable at a glance. */
    private function shortType(string $type): string
    {
        return (string) preg_replace('/[A-Za-z_][A-Za-z0-9_]*\\\\/', '', $type);
    }

    private function cli(): array
    {
        return [
            'new <name>'            => 'Scaffold a new standalone bot project',
            'doctor'                => 'Diagnose env, token, database, routes and webhook in one run',
            'routes'                => 'List every registered route in evaluation order',
            'upgrade'               => 'Check/apply scaffold changes after a library update (--dry-run to preview)',
            'poll'                  => 'Long-polling mode for local dev (no webhook)',
            'broadcast:run'         => 'Process pending rows in telegram_broadcasts',
            'migrate'               => 'Run pending database migrations',
            'migrate:status'        => 'Show applied / Pending / Untracked migrations',
            'migrate:rollback'      => 'Undo the most recent migration batch (--step=N, --all)',
            'webhook:set <url>'     => 'Register the webhook with Telegram',
            'webhook:delete'        => 'Remove the webhook (required before poll works)',
            'webhook:info'          => 'Show the currently registered webhook',
            'make:command <Name>'   => 'Generate app/Commands/<Name>.php',
            'make:callback <Name>'  => 'Generate app/Callbacks/<Name>.php',
            'make:middleware <Name>' => 'Generate app/Middleware/<Name>.php',
            'make:flow <Name>'      => 'Generate app/Flows/<Name>.php',
            'make:text <Name>'      => 'Generate app/Texts/<Name>.php',
            'make:service <Name>'   => 'Generate app/Services/<Name>.php',
            'make:keyboard <Name>'  => 'Generate app/Keyboards/<Name>.php (implements KeyboardInterface)',
            'make:model <Name>'     => 'Generate app/Models/<Name>.php (plain Eloquent model)',
            'make:handler <Name>'   => 'Generate app/Handlers/<Name>.php (handler group with a static register())',
            'make:migration <name>' => 'Generate a timestamped migration in database/migrations/ (--model also scaffolds the matching model)',
            'ai:manifest'           => 'Regenerate .ai/api.json',
        ];
    }

    private function version(): string
    {
        // The library's own composer.json is checked first: inside this repo
        // InstalledVersions resolves to the checked-out branch name
        // ("dev-main"), which is not what belongs in a published manifest.
        $composer = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (is_array($data) && !empty($data['version'])) {
                return (string) $data['version'];
            }
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                return (string) \Composer\InstalledVersions::getPrettyVersion('devflow/telegram-bot');
            } catch (\Throwable) {
                // Not installed as a package — nothing better to report.
            }
        }

        return 'unknown';
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }

    private function line(string $msg): void
    {
        echo "{$msg}\n";
    }
}
