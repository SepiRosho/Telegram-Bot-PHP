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
| [11 — Keyboards](11-keyboards.md) | `Keyboard::inline()`, `Keyboard::reply()`, `Keyboard::button()`, `Keyboard::url()`, `Keyboard::remove()`, `Keyboard::paginate()` |
| [12 — i18n](12-i18n.md) | `Support\Lang` + `$ctx->t()` — key-based translations with per-user locale, the recommended i18n approach |

Start with [01 — Installation](01-installation.md) if this is your first time.
