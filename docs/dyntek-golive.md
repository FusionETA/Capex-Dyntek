# Dyntek Portal Go-Live Runbook

How to stand up the Capex app on **Dyntek's production Bitrix24 portal**. Dyntek is a
separate portal from the Fusion test portal, so it gets its **own** Local App
registration, OAuth tokens, and `APP_ENV=prod` config. The code is identical to test;
only the config and the portal differ.

- **Prod subfolder:** `/web/capex-dyntek/` on FusionETA cPanel
- **Handler URL:** `https://fusioneta.com.my/web/capex-dyntek/public/index.php`
- **Install URL:** `https://fusioneta.com.my/web/capex-dyntek/public/install.php`
- The app is **hosted on FusionETA** but **connects to Dyntek's portal** via OAuth —
  so the handler URL stays on `fusioneta.com.my` even though it's Dyntek's app.

> The app is hosted on FusionETA cPanel; only OAuth points at Dyntek. Test
> (`/web/capex-test/`) and prod (`/web/capex-dyntek/`) never share files or state.

---

## Phase 0 — Confirm with Dyntek before building

These drive `app.prod.php`, so lock them first:

- [ ] **Regions** in use (test: SG/HK/MY/ID) — must match `src/Domain/Options.php` if different
- [ ] **Cost centres, categories, currencies** (in `Options.php`) + **FX rates** to SGD
- [ ] **Amount bands** (approval ceilings): HOD / Regional Finance / Country MD
- [ ] **Financial year** (`current_fy`) — calendar year assumed
- [ ] **People → roles**: approvers per band, Finance (targets), Group CFO
- [ ] **Department → role** mappings (optional)
- [ ] **`portal_admin_role`**: `SYSTEM_ADMIN` (open + manage access) or `GROUP_CFO` (full)
- [ ] Corp targets + sales-target figures per region (can be entered in-app after)

## Phase 1 — Register the Local App on Dyntek's portal

Developer resources → **add a Local application**:

- **Handler path:** `https://fusioneta.com.my/web/capex-dyntek/public/index.php`
- **Initial install path:** `https://fusioneta.com.my/web/capex-dyntek/public/install.php`
- **Scopes:** `crm`, `user`, `department`, `placement`
  - `department` is required for department-based access — include it from the start.
- Tick "uses only API / no menu" as appropriate; save.
- Copy the new **client_id** and **client_secret**.

## Phase 2 — Prod config + environment

1. `cp capex-app/config/app.php.example capex-app/config/app.prod.php`
2. Fill in `app.prod.php`:
   - `oauth.client_id / client_secret / portal_domain` (Dyntek's)
   - `current_fy`, `fx_rates`, `authority_bands`
   - `access` seed — **at least one `GROUP_CFO`** (bootstrap admin, so nobody's locked out)
   - `portal_admin_role`
   - Leave `entities` / `stages` / `fields` placeholders — discovery fills them.
3. Prod deploy-root `.htaccess` must set the env:
   ```apache
   SetEnv APP_ENV prod
   ```
   (Copy `capex-app/.htaccess.example` → the deployed `.htaccess`, ensure `APP_ENV prod`.)

## Phase 3 — Deploy code (never `var/`)

Deploy `src/`, `public/`, `config/` into `/capex-dyntek/` via the cpanel-deploy script,
each subfolder separately. **Do not** mirror `var/` — it holds live tokens/access/audit.

```bash
bash scripts/ftp_deploy.sh ./capex-app/src     capex-dyntek/src
bash scripts/ftp_deploy.sh ./capex-app/public  capex-dyntek/public
bash scripts/ftp_deploy.sh ./capex-app/config  capex-dyntek/config
```
Also upload the deploy-root `.htaccess` (with `APP_ENV prod`) to `/capex-dyntek/`.

## Phase 4 — Provision (browser install)

Open the app on Dyntek (or hit the install path). The browser install page
(`install.php` + `provision.js`), running in the **admin's session**, will:

- create the two SPAs: **Capex Request** and **Sales Target**
- create all user fields, incl. `approval_note`, `attachment`, `corp_target`
- add the custom **Approved** stage and **prune** Bitrix's default `CLIENT` + `SUCCESS`
  stages → pipeline becomes **Draft → Submitted → Approved (+ Rejected)**
- bind the Capex menu placements

Watch the log for each field/stage line and "All done".

## Phase 5 — Discover field codes

The install stores fresh OAuth tokens **server-side** and rotates them, so pull them
before discovery:

```bash
# pull the prod token the install just wrote
lftp ... get /capex-dyntek/var/tokens.prod.sqlite -o capex-app/var/tokens.prod.sqlite
# read the portal back → writes config/generated.prod.php
APP_ENV=prod php capex-app/bin/provision.php --discover
# deploy the discovered codes
bash scripts/ftp_deploy.sh ./capex-app/config/generated.prod.php capex-dyntek/config
```

## Phase 6 — Seed business data

- **Amount bands:** Manage Access → Approval amount bands
- **Sales targets / corp targets:** Sales Targets → add a row per region for `current_fy`,
  then enter Corp target / New target / Current met
- Adding a later year (e.g. 2027) is just adding rows — no code change

## Phase 7 — Configure access

- Portal admins get in automatically on first open (per `portal_admin_role`)
- **Department access:** Manage Access → Department access → grant a role to a department
- **Individuals:** Manage Access → Grant access to someone (overrides department)

## Phase 8 — Smoke test on prod

Run the full flow end-to-end:

1. New request (with an attachment) → routed by amount to the right approver
2. Approvals → open detail → set cost centre + note → Approve
3. History → both dates show; open one → edit (payback, amount…) → change logged
4. Dashboard → switch financial year → region/category/month breakdowns
5. Sales Targets → edit corp/new/current; switch year; add a row
6. Manage Access → grant by individual + department; change amount bands
7. Confirm a non-listed **non-admin** gets the "No access" screen

## Phase 9 — Handover

- Confirm at least one Group CFO + the Finance user are set
- Note the `/diag` URL returns `{"ok":true}` from within the portal
- Hand over the demo script (`docs/demo-script.md`) and permissions runbook

---

## Gotchas we already hit on test (don't repeat)

- **SPA fields can only be created in an admin browser session** — that's why provisioning
  runs in `provision.js`, not on the server. `user.admin`/`department.get`/discovery are
  read-only and use the stored server token.
- **The OAuth token rotates on every (re)install** — always pull `var/tokens.prod.sqlite`
  from the server before running `--discover`, or it fails auth.
- **Never deploy/mirror `var/`** — it holds the live token, access list, audit log, bands,
  and department map. Deploy only `src/`, `public/`, `config/`.
- **Bitrix-level app User access** is separate from the app's own roles — if a user gets
  Bitrix's own "Access denied" before the app loads, grant them the app under
  Installed apps → Capex → User access (this is a portal setting, not a REST call).
