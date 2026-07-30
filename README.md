# Capex Management System

Bitrix24 Smart Process Automation + a small PHP companion app, hosted on FusionETA cPanel.

- **Record store:** Bitrix24 SPA (3 entity types) — Capex Request, Budget Envelope, Sales Target
- **Automation:** Bitrix24 BPM robots (approval tasks, notifications, SLA)
- **Companion app:** PHP 8.2, no framework, no build step
- **Group currency:** SGD. One portal, region as a field.

Bitrix24 is the database. The companion app stores **only** OAuth tokens and an error log.

## Repo layout

```
capex-app/
  public/            # app entry, install, static assets
  src/
    Bitrix/          # REST client + OAuth
    Repo/            # crm.item.* wrappers per entity
    Domain/          # BudgetEngine (pure), Money
    Http/            # Router + Handlers
    View/            # PHP templates, one per screen
  config/            # app.php (from app.php.example)
  var/               # tokens.sqlite + app.log (gitignored)
  tests/             # BudgetEngineTest.php
```

## Getting started

1. Copy `capex-app/config/app.php.example` to `capex-app/config/app.php` and fill in the
   client id/secret, portal domain, and entity type ids (see the build plan, §3).
2. Deploy `capex-app/` to cPanel. Bitrix24 posts `AUTH_ID` to `public/index.php` on install/open.
3. Run the tests: `php capex-app/tests/BudgetEngineTest.php`.

## Rules for whoever codes this

- Currency is **integer cents** everywhere. No floats.
- Never store a capex figure on the host. Bitrix24 is the database.
- Totals are always **re-derived** by summing records, never incremented.
- Every server handler **re-checks** the caller's Bitrix24 rights.
- If a feature can be a robot instead of code, make it a robot.
- Field codes live in `config/app.php` only — no string literals in logic.
