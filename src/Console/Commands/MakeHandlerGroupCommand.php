<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeHandlerGroupCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/handler.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Handlers';
    }

    protected function getNamespace(): string
    {
        return 'App\\Handlers';
    }

    protected function getLabel(): string
    {
        return 'Handler group';
    }

    protected function getCommandUsage(): string
    {
        return 'make:handler';
    }
}
