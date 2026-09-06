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

The checklist is organized by the library version that introduced each item, and every entry
prints with a `[x.y.z]` tag saying which version it's from — the header line also names the highest
version the installed library's checklist covers (`checking this project's scaffold against
v1.17.0`). There's no cutoff: a project on any older version runs the *entire* history in one pass
and converges to the same end state, regardless of which version it started from. Every item is
independent and safe to re-run as many times as you like — an already-satisfied one just reports OK
and does nothing.

Three kinds of entry:

**Applied automatically** (purely additive — nothing existing is overwritten):

- `app/Keyboards/` — created if missing (target directory for `make:keyboard`, v1.15.0).
- `app/Emojis.php` — created with an empty registry if missing (consumed by `Support\Emoji`,
  v1.15.0). If you already have one, it's left untouched.
- `LANGUAGE_CODE` (v1.14.0) / `LANG_AUTO_FALLBACK` (v1.15.0) — appended to `.env` and
  `.env.example` if missing, without touching anything else in the file.
- `.ai/api.json` — regenerated every run, independent of version. It's a generated, reflection-built
  file, so refreshing it is routine maintenance rather than a "fix."

**Reported, not auto-applied** — `bootstrap/app.php` is the one scaffolded file almost every
project has already hand-customized by the time an upgrade matters, so `upgrade` never edits it. If
any of the following config keys or wiring are missing, it prints the exact snippet to paste in:

- `'language_code' => env('LANGUAGE_CODE', 'auto')` (v1.14.0) — see [12-i18n.md](12-i18n.md).
- `'user_defaults' => fn (Update $update): array => [...]` (v1.14.0) — promotes `ADMIN_CHAT_ID` to
  `'superadmin'` on first contact. See [06-database.md](06-database.md#seeding-fields-on-first-contact).
- `'lang_auto_fallback' => filter_var(env('LANG_AUTO_FALLBACK', false), FILTER_VALIDATE_BOOLEAN)`
  (v1.15.0) — see [12-i18n.md](12-i18n.md#falling-back-across-every-shipped-language).
- `Emoji::registerMany(require __DIR__ . '/../app/Emojis.php');` (v1.15.0) — see
  [16-premium-emoji.md](16-premium-emoji.md).

After pasting those in, run `vendor/bin/devflow doctor` to confirm `bootstrap/app.php` still loads
without errors.

**Notes** — a version that shipped something new but needed no scaffold change (a new CLI command,
a new *optional* config key with a safe default) still gets an entry, so upgrading past it doesn't
mean missing what's new. These always print and are never counted as a problem:

- v1.16.0 — `make:handler`, `migrate:rollback` (new commands, nothing to wire).
- v1.17.0 — `retry_transient`, `retry_jitter`, `retry_strategy`, `on_retry`, `sleeper` (new optional
  `Bot::init()` config, see [14-files-and-limits.md](14-files-and-limits.md)).

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

## Extending the checklist (contributing to the library)

`devflow upgrade`'s checklist lives under `src/Console/Upgrades/` in the library itself, one file
per version (`1.15.0.php`, `1.16.0.php`, ...), each returning a list of `UpgradeStep`. `UpgradeCommand`
loads every file in version order and runs them uniformly — adding a new version's entry means
adding a new file there, not touching `UpgradeCommand` itself. See `UpgradeStep`'s three factory
methods (`fixable()`, `manual()`, `note()`) and the `envKey()` / `bootstrapMarker()` shortcuts for
the two most common shapes. See AGENTS.md's "Shipping a new version" note — this file is part of
cutting a release, the same way bumping the version constant is.

---

## Next step

[README.md](README.md) — back to the guide index.
