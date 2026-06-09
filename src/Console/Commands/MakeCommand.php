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

        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $className)) {
            $this->error("Class name must be PascalCase (e.g. StartCommand).");
            exit(1);
        }

        $stub = file_get_contents($this->getStubPath());
        if ($stub === false) {
            $this->error("Stub file not found: {$this->getStubPath()}");
            exit(1);
        }

        $flowSlug  = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($className)));
        $content   = str_replace(
            ['{{ ClassName }}', '{{ Namespace }}', '{{ FlowSlug }}'],
            [$className, $this->getNamespace(), ltrim($flowSlug, '_')],
            $stub
        );

        $dir  = $this->getTargetDirectory();
        $file = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->error("Could not create directory: {$dir}");
            exit(1);
        }

        if (file_exists($file)) {
            $this->error("{$this->getLabel()} [{$className}] already exists.");
            exit(1);
        }

        file_put_contents($file, $content);
        $this->success("{$this->getLabel()} [{$className}] created: {$this->relativePath($file)}");
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
            ? ltrim(str_replace($cwd, '', $absolute), DIRECTORY_SEPARATOR)
            : $absolute;
    }
}
