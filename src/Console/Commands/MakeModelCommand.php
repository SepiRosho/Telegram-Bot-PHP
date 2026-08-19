<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeModelCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/model.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Models';
    }

    protected function getNamespace(): string
    {
        return 'App\\Models';
    }

    protected function getLabel(): string
    {
        return 'Model';
    }

    protected function getCommandUsage(): string
    {
        return 'make:model';
    }
}
