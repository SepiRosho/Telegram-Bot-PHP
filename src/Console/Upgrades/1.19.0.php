<?php

// allowed_updates: choose which update types Telegram delivers.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'New optional Bot::init() config `allowed_updates` (array or comma-separated string, or "all"), '
        . 'plus `webhook:set --allowed-updates=...`. Telegram does not deliver chat_member, message_reaction '
        . 'or message_reaction_count unless they are requested by name, so onChatMember()/onMessageReaction() '
        . 'handlers stay silent until you set it. `devflow poll` honours the same key. No scaffold change is '
        . 'required; see AGENTS.md §4.',
    ),
];
