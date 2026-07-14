<?php

namespace Devflow\TelegramBot\Support;

/**
 * Fluent builder for Telegram keyboard markup arrays.
 *
 * Usage (inline):
 *   $ctx->reply('Choose:', ['reply_markup' => Keyboard::inline([
 *       [Keyboard::button('📊 Status', 'bot_status'), Keyboard::button('👥 Users', 'admin_users')],
 *       [Keyboard::button('⬅️ Back', 'go_back')],
 *   ])]);
 *
 * Usage (reply):
 *   $ctx->reply('Welcome!', ['reply_markup' => Keyboard::reply([
 *       ['📋 My Account', '🔔 Notifications'],
 *       ['❓ Help'],
 *   ])]);
 */
class Keyboard
{
    /**
     * Build an inline keyboard markup array.
     * Each row is an array of buttons from Keyboard::button() or Keyboard::url().
     */
    public static function inline(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /**
     * Build a reply keyboard markup array.
     * Each row is an array of strings (button labels) or ['text' => ..., 'request_contact' => true] arrays.
     */
    public static function reply(array $rows, bool $resize = true, bool $oneTime = false): array
    {
        $markup = [
            'keyboard' => array_map(
                fn(array $row) => array_map(
                    fn($btn) => is_string($btn) ? ['text' => $btn] : $btn,
                    $row
                ),
                $rows
            ),
        ];

        if ($resize) {
            $markup['resize_keyboard'] = true;
        }

        if ($oneTime) {
            $markup['one_time_keyboard'] = true;
        }

        return $markup;
    }

    /**
     * Create an inline button that sends a callback query.
     */
    public static function button(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    /**
     * Create an inline button that opens a URL.
     */
    public static function url(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**
     * Remove the reply keyboard from the user's client.
     */
    public static function remove(): array
    {
        return ['remove_keyboard' => true];
    }

    /**
     * Build a paginated inline keyboard: one row per item (via $renderRow),
     * plus a prev/page/next navigation row when there's more than one page.
     *
     * $renderRow receives a single item and must return one keyboard row,
     * e.g. fn($user) => [Keyboard::button("Unban {$user->first_name}", "unban_{$user->id}")]
     *
     * Navigation callback data is "{$cbPrefix}page_{N}" — check for that
     * prefix in your callback handler to move between pages.
     */
    public static function paginate(array $items, int $page, callable $renderRow, string $cbPrefix, int $perPage = 10): array
    {
        $totalPages = max(1, (int) ceil(count($items) / $perPage));
        $page = max(1, min($page, $totalPages));

        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);
        $rows = array_map($renderRow, $slice);

        if ($totalPages > 1) {
            $nav = [];
            if ($page > 1) {
                $nav[] = self::button('⬅️', "{$cbPrefix}page_" . ($page - 1));
            }
            $nav[] = self::button("{$page}/{$totalPages}", "{$cbPrefix}noop");
            if ($page < $totalPages) {
                $nav[] = self::button('➡️', "{$cbPrefix}page_" . ($page + 1));
            }
            $rows[] = $nav;
        }

        return self::inline($rows);
    }
}
