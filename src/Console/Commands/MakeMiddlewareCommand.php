<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeMiddlewareCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/middleware.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Middleware';
    }

    protected function getNamespace(): string
    {
        return 'App\\Middleware';
    }

    protected function getLabel(): string
    {
        return 'Middleware';
    }

    protected function getCommandUsage(): string
    {
        return 'make:middleware';
    }
}
