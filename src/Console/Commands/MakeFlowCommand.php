<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeFlowCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/flow.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Flows';
    }

    protected function getNamespace(): string
    {
        return 'App\\Flows';
    }

    protected function getLabel(): string
    {
        return 'Wizard flow';
    }

    protected function getCommandUsage(): string
    {
        return 'make:flow';
    }
}
