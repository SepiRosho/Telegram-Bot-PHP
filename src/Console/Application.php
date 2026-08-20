<?php

namespace Devflow\TelegramBot\Console;

use Devflow\TelegramBot\Console\Commands\AiManifestCommand;
use Devflow\TelegramBot\Console\Commands\BroadcastRunCommand;
use Devflow\TelegramBot\Console\Commands\DoctorCommand;
use Devflow\TelegramBot\Console\Commands\MakeCallbackCommand;
use Devflow\TelegramBot\Console\Commands\MakeFlowCommand;
use Devflow\TelegramBot\Console\Commands\MakeHandlerCommand;
use Devflow\TelegramBot\Console\Commands\MakeHandlerGroupCommand;
use Devflow\TelegramBot\Console\Commands\MakeKeyboardCommand;
use Devflow\TelegramBot\Console\Commands\MakeMiddlewareCommand;
use Devflow\TelegramBot\Console\Commands\MakeMigrationCommand;
use Devflow\TelegramBot\Console\Commands\MakeModelCommand;
use Devflow\TelegramBot\Console\Commands\MakeServiceCommand;
use Devflow\TelegramBot\Console\Commands\MakeTextCommand;
use Devflow\TelegramBot\Console\Commands\MigrateCommand;
use Devflow\TelegramBot\Console\Commands\MigrateRollbackCommand;
use Devflow\TelegramBot\Console\Commands\MigrateStatusCommand;
use Devflow\TelegramBot\Console\Commands\NewProjectCommand;
use Devflow\TelegramBot\Console\Commands\PollCommand;
use Devflow\TelegramBot\Console\Commands\RoutesCommand;
use Devflow\TelegramBot\Console\Commands\UpgradeCommand;
use Devflow\TelegramBot\Console\Commands\WebhookCommand;

class Application
{
    private const VERSION = '1.16.0';

    private array $commands = [
        'new'               => NewProjectCommand::class,
        'doctor'            => DoctorCommand::class,
        'routes'            => RoutesCommand::class,
        'poll'              => PollCommand::class,
        'broadcast:run'     => BroadcastRunCommand::class,
        'migrate'           => MigrateCommand::class,
        'migrate:status'    => MigrateStatusCommand::class,
        'migrate:rollback'  => MigrateRollbackCommand::class,
        'webhook:set'       => WebhookCommand::class,
        'webhook:delete'    => WebhookCommand::class,
        'webhook:info'      => WebhookCommand::class,
        'make:command'      => MakeHandlerCommand::class,
        'make:callback'     => MakeCallbackCommand::class,
        'make:middleware'   => MakeMiddlewareCommand::class,
        'make:flow'         => MakeFlowCommand::class,
        'make:text'         => MakeTextCommand::class,
        'make:service'      => MakeServiceCommand::class,
        'make:keyboard'     => MakeKeyboardCommand::class,
        'make:model'        => MakeModelCommand::class,
        'make:handler'      => MakeHandlerGroupCommand::class,
        'make:migration'    => MakeMigrationCommand::class,
        'upgrade'           => UpgradeCommand::class,
        'ai:manifest'       => AiManifestCommand::class,
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

        $class = $this->commands[$command];

        // The three webhook:* names share one class, distinguished by action.
        $instance = $class === WebhookCommand::class
            ? new WebhookCommand(explode(':', $command)[1])
            : new $class();

        $instance->execute($args);
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

  \033[33mDiagnostics (run inside your project):\033[0m
    \033[32mdoctor\033[0m                        Check env, token, database, routes and webhook in one run
    \033[32mroutes\033[0m                        List every registered route in evaluation order
    \033[32mupgrade\033[0m [--dry-run]           Check/apply scaffold changes after a library update

  \033[33mRuntime commands (run inside your project):\033[0m
    \033[32mpoll\033[0m                          Start long-polling mode (local dev, no webhook)
      \033[32m--drop-pending\033[0m              …ignoring whatever queued up while the bot was down
    \033[32mbroadcast:run\033[0m                 Process pending broadcasts from the DB queue
    \033[32mmigrate\033[0m                       Run pending database migrations
    \033[32mmigrate:status\033[0m                Show which migrations have run
    \033[32mmigrate:rollback\033[0m [--step=N|--all]  Undo the most recent migration batch
    \033[32mwebhook:set\033[0m <https-url>       Register your webhook URL with Telegram
    \033[32mwebhook:delete\033[0m                Remove the webhook (required before polling)
    \033[32mwebhook:info\033[0m                  Show the currently registered webhook

  \033[33mCode generators (run inside your project):\033[0m
    \033[32mmake:command\033[0m <ClassName>      Generate a command handler  (app/Commands/)
    \033[32mmake:callback\033[0m <ClassName>     Generate a callback handler (app/Callbacks/)
    \033[32mmake:middleware\033[0m <ClassName>   Generate a middleware class  (app/Middleware/)
    \033[32mmake:flow\033[0m <ClassName>         Generate a wizard flow       (app/Flows/)
    \033[32mmake:text\033[0m <ClassName>         Generate a localized text class (app/Texts/)
    \033[32mmake:service\033[0m <ClassName>      Generate a service class     (app/Services/)
    \033[32mmake:keyboard\033[0m <ClassName>     Generate a reusable keyboard (app/Keyboards/)
    \033[32mmake:model\033[0m <ClassName>        Generate an Eloquent model   (app/Models/)
    \033[32mmake:handler\033[0m <ClassName>      Generate a handler group     (app/Handlers/)
    \033[32mmake:migration\033[0m <name> [--model]  Generate a migration     (database/migrations/)
      \033[32m--model\033[0m                     …and a matching model for a create_*_table migration

  \033[33mAI tooling:\033[0m
    \033[32mai:manifest\033[0m                   Regenerate .ai/api.json — a machine-readable index
                                  of the whole library surface for coding agents

  \033[33mExamples:\033[0m
    vendor/bin/devflow new my-telegram-bot
    vendor/bin/devflow doctor
    vendor/bin/devflow routes
    vendor/bin/devflow poll
    vendor/bin/devflow poll --drop-pending
    vendor/bin/devflow webhook:set https://example.com/public/webhook.php
    vendor/bin/devflow migrate
    vendor/bin/devflow migrate:rollback
    vendor/bin/devflow migrate:rollback --step=2
    vendor/bin/devflow migrate:rollback --all
    vendor/bin/devflow broadcast:run
    vendor/bin/devflow upgrade
    vendor/bin/devflow make:command HelpCommand
    vendor/bin/devflow make:middleware RateLimitMiddleware
    vendor/bin/devflow make:flow RegistrationFlow
    vendor/bin/devflow make:service NotificationService
    vendor/bin/devflow make:keyboard MainMenuKeyboard
    vendor/bin/devflow make:model Order
    vendor/bin/devflow make:handler ShopHandlers
    vendor/bin/devflow make:migration create_orders_table
    vendor/bin/devflow make:migration create_orders_table --model

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
