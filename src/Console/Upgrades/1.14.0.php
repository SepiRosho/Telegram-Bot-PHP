<?php

// Forceable language_code and working ADMIN_CHAT_ID auto-promotion.

use Devflow\TelegramBot\Console\Upgrades\UpgradeStep;

return [
    UpgradeStep::envKey('LANGUAGE_CODE', 'LANGUAGE_CODE=auto'),

    UpgradeStep::bootstrapMarker(
        marker: 'language_code',
        label: 'language_code',
        snippet: "'language_code'      => env('LANGUAGE_CODE', 'auto'),",
    ),

    UpgradeStep::bootstrapMarker(
        marker: 'user_defaults',
        label: 'user_defaults',
        snippet: <<<'PHP'
        'user_defaults'      => fn (\Devflow\TelegramBot\Types\Update $update): array => [
                'role' => env('ADMIN_CHAT_ID') && (string) $update->message?->from?->id === trim((string) env('ADMIN_CHAT_ID'))
                    ? 'superadmin'
                    : 'user',
            ],
        PHP,
    ),
];
