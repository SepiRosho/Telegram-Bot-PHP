<?php

// allowed_updates: choose which update types Telegram delivers.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::envKey(
        'ALLOWED_UPDATES',
        "# Which update types Telegram delivers, e.g. message,callback_query,chat_member (\"all\" = everything).\n"
        . "# Telegram does NOT send chat_member or message_reaction unless listed. Empty = keep Telegram's stored setting.\n"
        . 'ALLOWED_UPDATES=',
    ),

    UpgradeStep::bootstrapMarker(
        marker: 'allowed_updates',
        label: 'allowed_updates',
        snippet: "'allowed_updates'    => env('ALLOWED_UPDATES') ?: null,",
    ),

    UpgradeStep::note(
        'Telegram does not deliver chat_member, message_reaction or message_reaction_count unless they are '
        . 'requested by name, so onChatMember()/onMessageReaction() handlers stay silent until ALLOWED_UPDATES '
        . 'lists them and you re-run `webhook:set` (`devflow poll` picks it up on its own). '
        . '`webhook:set --allowed-updates=...` overrides it for one run; `devflow doctor` flags the mismatch.',
    ),
];
