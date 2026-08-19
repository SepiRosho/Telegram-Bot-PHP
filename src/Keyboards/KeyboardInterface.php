<?php

namespace Devflow\TelegramBot\Keyboards;

/**
 * A reusable, named keyboard definition. Implement one per keyboard that
 * appears in more than one place (a main menu, an admin panel, ...) instead
 * of rebuilding the same Keyboard::inline()/reply() call at every call site
 * — reordering a button then means editing one class instead of grepping
 * for every handler that renders it.
 *
 * $vars carries whatever the call site knows that the keyboard needs in
 * order to vary itself — e.g. ['isAdmin' => $ctx->user()?->isAdmin()] to
 * add an admin-only button — so the class stays decoupled from Context.
 *
 *   $ctx->reply('Main menu:', [
 *       'reply_markup' => MainMenuKeyboard::build(['isAdmin' => $ctx->user()?->isAdmin()]),
 *   ]);
 */
interface KeyboardInterface
{
    /** @return array The reply_markup array — pass straight through to $ctx->reply()/sendMessage(). */
    public static function build(array $vars = []): array;
}
