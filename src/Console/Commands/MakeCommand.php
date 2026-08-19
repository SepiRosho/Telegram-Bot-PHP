<?php

namespace Devflow\TelegramBot\Console\Commands;

abstract class MakeCommand
{
    abstract protected function getStubPath(): string;
    abstract protected function getTargetDirectory(): string;
    abstract protected function getNamespace(): string;
    abstract protected function getLabel(): string;
    abstract protected function getCommandUsage(): string;

    public function execute(array $args): void
    {
        $className = $args[0] ?? null;

        if (!$className) {
            $this->error("Usage: vendor/bin/devflow {$this->getCommandUsage()} <ClassName>");
            exit(1);
        }

        $result = $this->generate($className);

        if ($result !== true) {
            $this->error($result);
            exit(1);
        }
    }

    /**
     * Writes the class file and returns true, or an error message instead of
     * exiting the process — lets another command (e.g. `make:migration
     * --model`) generate a file inline without tearing down its own run.
     *
     * @return true|string
     */
    public function generate(string $className): bool|string
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $className)) {
            return 'Class name must be PascalCase (e.g. StartCommand).';
        }

        $stub = file_get_contents($this->getStubPath());
        if ($stub === false) {
            return "Stub file not found: {$this->getStubPath()}";
        }

        $flowSlug   = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($className)));
        $tableGuess = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly($className));
        $content    = str_replace(
            ['{{ ClassName }}', '{{ Namespace }}', '{{ FlowSlug }}', '{{ TableGuess }}'],
            [$className, $this->getNamespace(), ltrim($flowSlug, '_'), $tableGuess],
            $stub
        );

        $dir  = $this->getTargetDirectory();
        $file = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return "Could not create directory: {$dir}";
        }

        if (file_exists($file)) {
            return "{$this->getLabel()} [{$className}] already exists.";
        }

        file_put_contents($file, $content);
        $this->success("{$this->getLabel()} [{$className}] created: {$this->relativePath($file)}");

        return true;
    }

    protected function success(string $msg): void
    {
        echo "\033[32m✓\033[0m {$msg}\n";
    }

    protected function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }

    private function relativePath(string $absolute): string
    {
        $cwd = getcwd();
        return str_starts_with($absolute, $cwd)
            ? ltrim(str_replace($cwd, '', $absolute), '/\\')
            : $absolute;
    }
}
