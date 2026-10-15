<?php

// Forum-topic / business-aware replies, content routes, payment + group service messages.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::note(
        'Behaviour change: $ctx->reply() / replyWith*() / typing() now post into the forum topic (message_thread_id) '
        . 'or business connection (business_connection_id) of the message being answered, instead of the General topic '
        . '/ the bot itself. Pass \'message_thread_id\' => null in $options to keep the old behaviour for a call.',
    ),
    UpgradeStep::note(
        'New optional routes: onVideo, onVoice, onAudio, onVideoNote, onSticker, onAnimation, onLocation, onContact, '
        . 'onVenue, onSuccessfulPayment, onNewChatMembers, onLeftChatMember, onWebAppData. A payment bot should register '
        . 'onSuccessfulPayment to grant the purchase. No scaffold change required.',
    ),
    UpgradeStep::note(
        'Tests: Bot::fake()->dispatch() now applies the config given to Bot::fake() (allowed_chat_types, '
        . 'auto_answer_callbacks, ...). A test that relied on a group message reaching a private-only bot will now '
        . 'correctly not match; pass \'allowed_chat_types\' => null to the fake to keep it.',
    ),
];
