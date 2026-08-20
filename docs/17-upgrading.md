# 17 — Upgrading

`composer update devflow/telegram-bot` pulls in new library code, but a project scaffolded by an
older version doesn't automatically grow the new directories, files or `bootstrap/app.php` config
keys that newer features expect. `devflow upgrade` closes that gap.

---

## Running it

From your project root, after bumping the library version:

```bash
composer update devflow/telegram-bot
vendor/bin/devflow upgrade
```

Preview what it would do first with `--dry-run` — nothing is written to disk:

```bash
vendor/bin/devflow upgrade --dry-run
```

---

## What it checks

Every check is independent, and safe to re-run as many times as you like — an already-satisfied
check just reports OK and does nothing.

**Applied automatically** (purely additive — nothing existing is overwritten):

- `app/Keyboards/` — created if missing (target directory for `make:keyboard`, added in v1.15.0).
- `app/Emojis.php` — created with an empty registry if missing (consumed by `Support\Emoji`, added
  in v1.15.0). If you already have one, it's left untouched.
- `LANGUAGE_CODE` / `LANG_AUTO_FALLBACK` — appended to `.env` and `.env.example` if either key is
  missing, without touching anything else in the file.
- `.ai/api.json` — regenerated every run. It's a generated, reflection-built file, so refreshing it
  is routine maintenance rather than a "fix."

**Reported, not auto-applied** — `bootstrap/app.php` is the one scaffolded file almost every
project has already hand-customized by the time an upgrade matters, so `upgrade` never edits it. If
any of the following config keys or wiring are missing, it prints the exact snippet to paste in:

- `'language_code' => env('LANGUAGE_CODE', 'auto')` — see [12-i18n.md](12-i18n.md).
- `'lang_auto_fallback' => filter_var(env('LANG_AUTO_FALLBACK', false), FILTER_VALIDATE_BOOLEAN)` —
  see [12-i18n.md](12-i18n.md#falling-back-across-every-shipped-language).
- `'user_defaults' => fn (Update $update): array => [...]` — promotes `ADMIN_CHAT_ID` to
  `'superadmin'` on first contact. See [06-database.md](06-database.md#seeding-fields-on-first-contact).
- `Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');` — see
  [16-premium-emoji.md](16-premium-emoji.md).

After pasting those in, run `vendor/bin/devflow doctor` to confirm `bootstrap/app.php` still loads
without errors.

---

## Database schema changes

`upgrade` only touches the project scaffold — new columns or tables that a library update ships
(e.g. the `language` column added alongside forceable `language_code`) arrive as ordinary
migrations bundled with the package. Run the usual command to pick those up:

```bash
vendor/bin/devflow migrate
```

`migrate:status` shows what's pending; see [06-database.md](06-database.md) for the full migration
workflow, including [rolling one back](06-database.md#rolling-back-a-migration) if something goes
wrong.

---

## Next step

[README.md](README.md) — back to the guide index.
