<?php

namespace Devflow\TelegramBot\Console;

use Devflow\TelegramBot\Console\Commands\BroadcastRunCommand;
use Devflow\TelegramBot\Console\Commands\MakeCallbackCommand;
use Devflow\TelegramBot\Console\Commands\MakeFlowCommand;
use Devflow\TelegramBot\Console\Commands\MakeHandlerCommand;
use Devflow\TelegramBot\Console\Commands\MakeMiddlewareCommand;
use Devflow\TelegramBot\Console\Commands\MakeServiceCommand;
use Devflow\TelegramBot\Console\Commands\MakeTextCommand;
use Devflow\TelegramBot\Console\Commands\NewProjectCommand;
use Devflow\TelegramBot\Console\Commands\PollCommand;

class Application
{
    private const VERSION = '1.6.0';

    private array $commands = [
        'new'               => NewProjectCommand::class,
        'poll'              => PollCommand::class,
        'broadcast:run'     => BroadcastRunCommand::class,
        'make:command'      => MakeHandlerCommand::class,
        'make:callback'     => MakeCallbackCommand::class,
        'make:middleware'   => MakeMiddlewareCommand::class,
        'make:flow'         => MakeFlowCommand::class,
        'make:text'         => MakeTextCommand::class,
        'make:service'      => MakeServiceCommand::class,
    ];

    public function run(array $argv): void
    {
        $command = $argv[1] ?? null;
        $args    = array_slice($argv, 2);

        if (!$command || in_array($command, ['help', '--help', '-h'], true)) {
            $this->showHelp();
            return;
        }

        if (in_array($command, ['version', '--version', '-V'], true)) {
            $this->line('devflow/telegram-bot v' . self::VERSION);
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: {$command}");
            echo "\n";
            $this->showHelp();
            exit(1);
        }

        (new $this->commands[$command]())->execute($args);
    }

    private function showHelp(): void
    {
        $v = self::VERSION;
        echo <<<HELP

  \033[32mdevflow/telegram-bot\033[0m  v{$v}

  \033[33mUsage:\033[0m
    vendor/bin/devflow <command> [arguments]

  \033[33mScaffold a new project:\033[0m
    \033[32mnew\033[0m <project-name>           Create a new standalone bot project

  \033[33mRuntime commands (run inside your project):\033[0m
    \033[32mpoll\033[0m                          Start long-polling mode (local dev, no webhook)
    \033[32mbroadcast:run\033[0m                 Process pending broadcasts from the DB queue

  \033[33mCode generators (run inside your project):\033[0m
    \033[32mmake:command\033[0m <ClassName>      Generate a command handler  (app/Commands/)
    \033[32mmake:callback\033[0m <ClassName>     Generate a callback handler (app/Callbacks/)
    \033[32mmake:middleware\033[0m <ClassName>   Generate a middleware class  (app/Middleware/)
    \033[32mmake:flow\033[0m <ClassName>         Generate a wizard flow       (app/Flows/)
    \033[32mmake:text\033[0m <ClassName>         Generate a localized text class (app/Texts/)
    \033[32mmake:service\033[0m <ClassName>      Generate a service class     (app/Services/)

  \033[33mExamples:\033[0m
    vendor/bin/devflow new my-telegram-bot
    vendor/bin/devflow poll
    vendor/bin/devflow broadcast:run
    vendor/bin/devflow make:command HelpCommand
    vendor/bin/devflow make:middleware RateLimitMiddleware
    vendor/bin/devflow make:flow RegistrationFlow
    vendor/bin/devflow make:service NotificationService

HELP;
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗\033[0m {$msg}\n";
    }
}
