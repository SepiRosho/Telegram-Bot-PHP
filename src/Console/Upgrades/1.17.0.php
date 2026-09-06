<?php

// 429 error handling expansion: pluggable backoff, retry_transient, uploads retried.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'New optional Bot::init() config: retry_transient, retry_jitter, retry_strategy, '
        . 'on_retry, sleeper — expand 429/5xx retry handling. All are off/identity by default, '
        . 'so no scaffold change is required; add whichever are useful to bootstrap/app.php. '
        . 'See docs/14-files-and-limits.md.',
    ),
];
