<?php

namespace Devflow\TelegramBot\Console\Commands;

use Devflow\TelegramBot\Bot;
use Devflow\TelegramBot\Console\Commands\Concerns\BootsProject;
use Devflow\TelegramBot\Routing\Route;
use Devflow\TelegramBot\Routing\Router;

/**
 * Lists every route the project registers, in the order the router will
 * actually evaluate them — which is not registration order, since step routes
 * are promoted ahead of everything else by default. Seeing that order is the
 * quickest way to spot a broad onText() catch-all shadowing later routes.
 */
class RoutesCommand
{
    use BootsProject;

    public function execute(array $args): void
    {
        $this->requireProjectBootstrap();

        try {
            $instance = Bot::getInstance();
        } catch (\Throwable) {
            $this->error('Bot::init() was never called — check bootstrap/app.php.');
            exit(1);
        }

        $routes = $instance->router()->routes();

        if ($routes === []) {
            $this->line('No routes registered. Check Bot::loadHandlers() in bootstrap/app.php.');
            return;
        }

        $stepsFirst = $instance->config('step_routes_first', true) !== false;
        $ordered    = $stepsFirst ? $this->stepsFirst($routes) : $routes;
        $default    = $instance->config('allowed_chat_types');

        $rows = [];
        foreach ($ordered as $i => $route) {
            $rows[] = [
                (string) ($i + 1),
                $route->type,
                $route->pattern,
                $this->describeHandler($route->handler),
                $this->describeChatTypes($route, $default),
            ];
        }

        echo "\n";
        $this->table(['#', 'TYPE', 'PATTERN', 'HANDLER', 'CHATS'], $rows);
        echo "\n";

        $this->line(count($routes) . ' route(s). Evaluated top to bottom; the first match wins.');

        if ($stepsFirst) {
            $this->line("\033[90mStep routes are listed first because step_routes_first is enabled.\033[0m");
        }

        $globals = count($instance->router()->middlewares());
        if ($globals > 0) {
            $this->line("\033[90m{$globals} global middleware run before every route.\033[0m");
        }

        echo "\n";
    }

    /** @param list<Route> $routes @return list<Route> */
    private function stepsFirst(array $routes): array
    {
        $steps = array_values(array_filter($routes, fn(Route $r) => $r->type === 'step'));
        $rest  = array_values(array_filter($routes, fn(Route $r) => $r->type !== 'step'));

        return [...$steps, ...$rest];
    }

    private function describeHandler(mixed $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            $class = is_object($handler[0]) ? $handler[0]::class : (string) $handler[0];
            return $class . '::' . $handler[1];
        }

        if ($handler instanceof \Closure) {
            // A closure has no name, so the definition site is the only useful
            // identifier — and it is what you need in order to go edit it.
            $ref  = new \ReflectionFunction($handler);
            $file = $ref->getFileName();

            return $file === false
                ? 'Closure'
                : 'Closure @ ' . basename($file) . ':' . $ref->getStartLine();
        }

        return get_debug_type($handler);
    }

    private function describeChatTypes(Route $route, mixed $default): string
    {
        // Group/channel-only update types ignore the filter entirely, so
        // inheriting the bot's 'private' default and printing it here would
        // claim the opposite of what the router actually does.
        if (in_array($route->type, Router::CHAT_TYPE_EXEMPT, true)) {
            return "\033[90many (exempt)\033[0m";
        }

        $effective = $route->chatTypes ?? $default;

        if ($effective === null || $effective === []) {
            return 'any';
        }

        $label = implode(',', (array) $effective);

        // Flag routes that widen past the bot's default, since that is the
        // deliberate exception and worth seeing at a glance.
        return $route->chatTypes !== null ? "\033[36m{$label}\033[0m" : $label;
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    private function table(array $headers, array $rows): void
    {
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = mb_strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen($this->stripAnsi($cell)));
            }
        }

        $line = '';
        foreach ($headers as $i => $header) {
            $line .= "\033[1m" . $this->pad($header, $widths[$i]) . "\033[0m  ";
        }
        echo rtrim($line) . "\n";

        foreach ($rows as $row) {
            $line = '';
            foreach ($row as $i => $cell) {
                $line .= $this->pad($cell, $widths[$i]) . '  ';
            }
            echo rtrim($line) . "\n";
        }
    }

    /** Pads to a visible width, ignoring ANSI colour codes that occupy no columns. */
    private function pad(string $value, int $width): string
    {
        $visible = mb_strlen($this->stripAnsi($value));

        return $value . str_repeat(' ', max(0, $width - $visible));
    }

    private function stripAnsi(string $value): string
    {
        return (string) preg_replace('/\033\[[0-9;]*m/', '', $value);
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
