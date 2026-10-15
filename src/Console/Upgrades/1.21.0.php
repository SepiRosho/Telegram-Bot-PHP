<?php

// Laravel poll/broadcast commands, more webhook:set options, file download, self-hosted Bot API, doctor allowed_updates check.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'New optional Bot::init() config: `api_url` (self-hosted Bot API server) and `test_environment`. '
        . 'New TelegramApi::fileContents() / downloadFile() fetch a received file in one call. See docs/14-files-and-limits.md.',
    ),
    UpgradeStep::note(
        'webhook:set now also accepts --secret=, --max-connections=, --ip-address=, --certificate= and --drop-pending, '
        . 'and rejects a malformed secret (Telegram allows only A-Z a-z 0-9 _ -) before calling the API. '
        . '`devflow doctor` warns when a handler is registered for an update type missing from allowed_updates.',
    ),
];
