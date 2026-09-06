<?php

// Handler-group generator, migration rollback, the upgrade command itself.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'New commands available: `make:handler <Name>` (scaffold a handler group under '
        . 'app/Handlers/) and `migrate:rollback` (undo the most recent migration batch). '
        . 'No scaffold changes required.',
    ),
];
