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
   OAuth client id/secret, portal domain and `current_fy`. **Leave the entity ids at 0** —
   provisioning fills them in.
2. Deploy `capex-app/` to cPanel and register a Local Application in the portal whose handler
   points at `public/install.php`. Installing stores the OAuth tokens.
3. **Provision the SPAs** (creates the three Smart Processes, their stages and fields):
   ```
   php capex-app/bin/provision.php --dry-run   # report only
   php capex-app/bin/provision.php             # apply + write config/generated.php
   ```
4. Re-open the app so `install.php` binds the placements now that the entity ids exist.
5. Run the tests any time: `for t in BudgetEngine AuthStore Recalculator Provisioner; do php capex-app/tests/${t}Test.php; done`

### How the portal setup works

Bitrix24 owns the data; the app is a tenant of it. Two distinct steps:

- **Provisioning** (`bin/provision.php`, driven by `config/schema.php`) creates the SPAs,
  stages and user fields via REST, then **discovers** the `ufCrm_*` codes Bitrix assigned
  (matched by field title, not guessed) and writes them to `config/generated.php`, which is
  merged over `app.php` at boot. Idempotent — safe to re-run; it never deletes.
- **Installation** (`install.php`) stores OAuth tokens, binds the three screen placements and
  the `onCrmDynamicItemUpdate` webhook. It does **not** create data structures.

Deleting the app removes placements + the webhook binding only — the SPAs and records remain.

### Environments (test vs prod portal)

A Local Application is tied to one portal, so the Fusion test portal and the Dyntek prod
portal each need their **own** Local App registration (separate client id/secret). The app
keeps them isolated via `APP_ENV`:

| APP_ENV | Config | Token store | Generated codes |
|---|---|---|---|
| `test` | `config/app.test.php` | `var/tokens.test.sqlite` | `config/generated.test.php` |
| `prod` (default) | `config/app.prod.php` (or legacy `app.php`) | `var/tokens.prod.sqlite` (or `tokens.sqlite`) | `generated.prod.php` |

Set it per deployment in `.htaccess` (or the vhost):

```apache
SetEnv APP_ENV test
```

Same code, promoted test → prod by changing that one line. Each env installs, provisions and
stores tokens independently — nothing crosses over. With no `APP_ENV` set it falls back to the
legacy single-portal filenames, so existing setups keep working.

## Rules for whoever codes this

- Currency is **integer cents** everywhere. No floats.
- Never store a capex figure on the host. Bitrix24 is the database.
- Totals are always **re-derived** by summing records, never incremented.
- Every server handler **re-checks** the caller's Bitrix24 rights.
- If a feature can be a robot instead of code, make it a robot.
- Field codes live in `config/app.php` only — no string literals in logic.
