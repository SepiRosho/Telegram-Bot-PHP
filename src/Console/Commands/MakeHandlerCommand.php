<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeHandlerCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/command.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Commands';
    }

    protected function getNamespace(): string
    {
        return 'App\\Commands';
    }

    protected function getLabel(): string
    {
        return 'Command handler';
    }

    protected function getCommandUsage(): string
    {
        return 'make:command';
    }
}
