<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeCallbackCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/callback.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Callbacks';
    }

    protected function getNamespace(): string
    {
        return 'App\\Callbacks';
    }

    protected function getLabel(): string
    {
        return 'Callback handler';
    }

    protected function getCommandUsage(): string
    {
        return 'make:callback';
    }
}
