<?php

namespace Devflow\TelegramBot\Support;

use Devflow\TelegramBot\Api\InputFile;

/**
 * Builds and validates the options array for setWebhook, so
 * `devflow webhook:set` and `php artisan telegram:set-webhook` accept exactly
 * the same flags and reject bad input the same way — before a request is made,
 * with a message that names the problem instead of Telegram's bare 400.
 */
final class WebhookOptions
{
    /**
     * @param array{
     *     secret?: ?string,
     *     allowed_updates?: string|array|null,
     *     drop_pending?: bool,
     *     max_connections?: string|int|null,
     *     ip_address?: ?string,
     *     certificate?: ?string,
     * } $input Raw values, as typed on a command line (strings) or read from config.
     *
     * @return array<string, mixed> setWebhook's optional parameters; only what was asked for.
     *
     * @throws \InvalidArgumentException
     */
    public static function build(array $input): array
    {
        $options = [];

        $secret = $input['secret'] ?? null;
        if ($secret !== null && $secret !== '') {
            // Telegram rejects anything else with a generic 400.
            if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', (string) $secret)) {
                throw new \InvalidArgumentException(
                    'The webhook secret must be 1–256 characters of A-Z, a-z, 0-9, "_" and "-" only.'
                );
            }
            $options['secret_token'] = (string) $secret;
        }

        $allowed = AllowedUpdates::parse($input['allowed_updates'] ?? null);
        if ($allowed !== null) {
            $options['allowed_updates'] = $allowed;
        }

        if (!empty($input['drop_pending'])) {
            $options['drop_pending_updates'] = true;
        }

        $max = $input['max_connections'] ?? null;
        if ($max !== null && $max !== '') {
            if (!ctype_digit((string) $max) || (int) $max < 1 || (int) $max > 100) {
                throw new \InvalidArgumentException('max_connections must be a whole number from 1 to 100.');
            }
            $options['max_connections'] = (int) $max;
        }

        $ip = $input['ip_address'] ?? null;
        if ($ip !== null && $ip !== '') {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new \InvalidArgumentException("ip_address \"{$ip}\" is not a valid IP address.");
            }
            $options['ip_address'] = (string) $ip;
        }

        $certificate = $input['certificate'] ?? null;
        if ($certificate !== null && $certificate !== '') {
            if (!is_file($certificate) || !is_readable($certificate)) {
                throw new \InvalidArgumentException("Certificate file not found or unreadable: {$certificate}");
            }
            $options['certificate'] = InputFile::path($certificate);
        }

        return $options;
    }

    /** One-line summary of what is about to be sent, for the command's output. */
    public static function describe(array $options): array
    {
        $lines = [];

        if (isset($options['secret_token'])) {
            $lines[] = 'Secret token: set';
        }
        if (isset($options['allowed_updates'])) {
            $lines[] = 'Allowed updates: ' . ($options['allowed_updates'] === [] ? '(Telegram default)' : implode(', ', $options['allowed_updates']));
        }
        if (isset($options['max_connections'])) {
            $lines[] = "Max connections: {$options['max_connections']}";
        }
        if (isset($options['ip_address'])) {
            $lines[] = "IP address: {$options['ip_address']}";
        }
        if (isset($options['certificate'])) {
            $lines[] = 'Custom certificate: uploaded';
        }
        if (!empty($options['drop_pending_updates'])) {
            $lines[] = 'Dropping pending updates';
        }

        return $lines;
    }
}
