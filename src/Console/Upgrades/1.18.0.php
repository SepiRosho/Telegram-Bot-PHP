<?php

// Laravel integration fixes; one schema change that reaches standalone projects.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'telegram_users.referral_code is now UNIQUE (and last_activity_at updates on write) for databases created '
        . 'from this version on, matching the bundled .sql files and the Laravel migrations. A database you already '
        . 'migrated keeps its old definition — `devflow migrate` does not re-run applied migrations. If you rely on '
        . 'referral codes being unique, add the index yourself: '
        . 'ALTER TABLE telegram_users ADD UNIQUE telegram_users_referral_code_unique (referral_code);',
    ),
];
