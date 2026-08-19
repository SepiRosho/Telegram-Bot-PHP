<?php

namespace Devflow\TelegramBot\Console\Commands;

class MakeKeyboardCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../Stubs/keyboard.stub';
    }

    protected function getTargetDirectory(): string
    {
        return getcwd() . '/app/Keyboards';
    }

    protected function getNamespace(): string
    {
        return 'App\\Keyboards';
    }

    protected function getLabel(): string
    {
        return 'Keyboard';
    }

    protected function getCommandUsage(): string
    {
        return 'make:keyboard';
    }
}
