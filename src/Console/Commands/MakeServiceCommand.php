<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeServiceCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/service.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Services';
    }

    protected function getNamespace(): string
    {
        return 'App\\Services';
    }

    protected function getLabel(): string
    {
        return 'Service';
    }

    protected function getCommandUsage(): string
    {
        return 'make:service';
    }
}
