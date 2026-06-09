<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeTextCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/text.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Texts';
    }

    protected function getNamespace(): string
    {
        return 'App\\Texts';
    }

    protected function getLabel(): string
    {
        return 'Text class';
    }

    protected function getCommandUsage(): string
    {
        return 'make:text';
    }
}
