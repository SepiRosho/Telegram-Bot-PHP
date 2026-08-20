# devflow/telegram-bot — Guides

Step-by-step guides for building a bot with this library.

| Guide | What it covers |
|---|---|
| [01 — Installation](01-installation.md) | Scaffold a project, folder structure, namespaces, autoloading, .env |
| [02 — Your First Bot](02-first-bot.md) | Get a token, set your webhook, receive your first message |
| [03 — Handlers](03-handlers.md) | Commands, text, callbacks, photos; `onStep`; splitting into handler groups |
| [04 — Context](04-context.md) | Everything `$ctx` can do; `from()` vs `user()` explained |
| [05 — Middleware](05-middleware.md) | Run code before every handler; ban checks, logging, DB-backed rate limiting |
| [06 — Database](06-database.md) | phpMyAdmin setup, enable user tracking and banning |
| [07 — Wizard Flows](07-flows.md) | Multi-step conversations using `step` and `temp` data |
| [08 — Local Dev](08-local-dev.md) | ngrok and Cloudflare Tunnel for testing on localhost; `Bot::fake()` for testing handlers without a token, webhook, or database |
| [09 — Localized Texts](09-texts.md) | `BotText` classes for multi-language messages with `{variable}` placeholders (legacy — see 12) |
| [10 — Handler Groups](10-handler-groups.md) | Split large bots across multiple handler files |
| [11 — Keyboards](11-keyboards.md) | `Keyboard::inline()`, `Keyboard::reply()`, `Keyboard::button()`, `Keyboard::url()`, `Keyboard::remove()`, `Keyboard::paginate()`, reusable `KeyboardInterface` classes via `make:keyboard` |
| [12 — i18n](12-i18n.md) | `Support\Lang` + `$ctx->t()` — key-based translations with per-user locale, the recommended i18n approach |
| [13 — Chat Types](13-chat-types.md) | Restricting a bot to private chats, why it matters, and letting specific routes into groups |
| [14 — Files & Limits](14-files-and-limits.md) | Uploading local files with `InputFile`; automatic 429 / `retry_after` handling |
| [15 — Errors & Reliability](15-errors.md) | Telling "user blocked the bot" apart from a real bug; polling backoff; `poll --drop-pending` |
| [16 — Premium Emoji](16-premium-emoji.md) | `Support\Emoji` — register premium emoji by name, use `:shortcode:` syntax instead of raw `<tg-emoji>`/`tg://emoji` markup |
| [17 — Upgrading](17-upgrading.md) | `devflow upgrade` — bring an existing project's scaffold current after a library update; `migrate:rollback` |

Start with [01 — Installation](01-installation.md) if this is your first time.

## Diagnostics

Two commands answer most "why isn't this working?" questions without reading any of the above:

```bash
vendor/bin/devflow doctor   # PHP extensions, .env, token, database, routes, webhook — in one pass
vendor/bin/devflow routes   # every registered route, in the order the router evaluates them
```

## Building with a coding agent

[`AGENTS.md`](../AGENTS.md) in the package root is a single dense reference covering this entire
library — written for AI coding assistants, which burn a lot of tokens reading tutorial-shaped docs
like these. Point your agent there instead of at `docs/` or `src/`.

`vendor/bin/devflow ai:manifest` generates `.ai/api.json`: every route type, `Context` method,
Telegram API method and config key, extracted by reflection so it can never drift from the code.
