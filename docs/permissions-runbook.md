# Capex — Permissions Runbook (Bitrix24 SPA access rights)

Who can see, edit, and approve Capex records is enforced by **Bitrix24's native Smart
Process access permissions** — not the companion app. This is a one-time manual setup
per portal (build plan §3.4). Do it once on the Fusion test portal, verify, then repeat
on the Dyntek prod portal.

> **Why manual:** Bitrix SPA permissions are role-based and tied to real portal
> users/departments. They're the authoritative gate on the *records*. The app's screens
> add their own checks on top, but the SPA permissions are what stop, e.g., one
> department reading another's requests.

---

## 1. Roles

Create these six roles (each SPA has its own access-rights table; the roles below apply
mainly to **Capex Request**; Budget Envelope and Sales Target are Finance-only to edit).

| Role | Who | Purpose |
|---|---|---|
| Requester | Any staff who raises capex | Create + submit their own requests |
| HOD | Head of Department | Gate A — first approval |
| Regional Finance | Finance per region | Gate B — budget/GL check |
| Country MD | Managing Director per country | Higher-band approval |
| Group CFO | Group CFO | Top-band + all OVER-budget approvals |
| System Admin | IT / app admin | Config + budget edit, **no approve rights** |

---

## 2. Where to set it

Bitrix24 → **CRM** → open the **Capex Request** Smart Process → **Settings (⚙) → Access
permissions** (Bitrix wording varies: "Access rights" / "Permissions"). Repeat for
**Budget Envelope** and **Sales Target**.

For each role: add the role, assign the users/departments, then set the rights below.
Bitrix rights are per-action (Read / Add / Update / Delete / Export) with a **scope**
(All / By department / Personal), plus **stage-move** permissions (which stages a role
may move an item *to*).

---

## 3. Capex Request — rights matrix

| Role | Read | Add | Update | Delete | Stage moves allowed |
|---|---|---|---|---|---|
| Requester | Own department | ✅ | Own, only in Draft/Submitted | Own draft | Draft → Submitted |
| HOD | Own department | — | Items at HOD review | — | Submitted → HOD review; HOD review → Finance review or Rejected |
| Regional Finance | Own region | — | Items at Finance review (fills GL code) | — | Finance review → Approved or Rejected |
| Country MD | Own region | — | — | — | (per authority bands — see §5) |
| Group CFO | All | — | — | — | Approve any OVER item; top band → Approved |
| System Admin | All | — | Config fields only | — | **None** (no approve) |

**Principle (from the plan):** *grant stage-move rights only to the role that owns that
gate.* A Requester cannot move past Submitted; only Finance moves to Approved; only the
authorised approver (by amount band) can push an OVER item through.

---

## 4. Budget Envelope & Sales Target — rights

| Role | Budget Envelope | Sales Target |
|---|---|---|
| Regional Finance / Group CFO | Read + **Update** | Read + **Update** |
| Everyone else | **Read only** | **Read only** |
| System Admin | Read + Update (create envelopes) | Read + Update |

Committed/spent figures on the envelope are **app-written** — leave them editable only by
Finance/Admin, but note the app overwrites them on every recalculation.

---

## 5. Authority bands — ⚠️ CONFIRM BEFORE M5 (Open Item #1)

Which role gives final approval depends on the SGD amount. These are **placeholders** —
confirm with the client before wiring approval robots (M5):

| Amount (SGD) | Final approver |
|---|---|
| ≤ 50,000 | HOD |
| 50,000 – 250,000 | Regional Finance |
| 250,000 – 1,000,000 | Country MD |
| > 1,000,000 | Group CFO |
| **Any OVER-budget** | **Group CFO** (regardless of amount) |

These bands live in `config/app.php` → `authority_bands` (in **integer cents**) and are
used by `Domain\BudgetEngine::authorityFor()`. Update both the config and this table when
confirmed.

---

## 6. Important: the app runs with service permissions

The companion app calls Bitrix with the **installed app's OAuth token** (admin-level), so
app actions are **not** automatically limited by the viewing user's role. Therefore:

- **Submission** (in-app "New request") is open to any portal user — that's the Requester
  role, and it only creates a record in Submitted. Fine.
- **Approvals** (in-app, coming next) must have the app **check the caller's role itself**
  before moving a stage, because Bitrix won't stop the service token. The role→user
  mapping for that check is the piece to decide alongside the authority bands above.

The SPA permissions in this runbook remain the real gate whenever users act **directly in
Bitrix24** (Kanban, forms), which is the fallback path if the app is unavailable.

---

## 7. Verify

- Log in as a test Requester → can create + submit, cannot see another department's items,
  cannot move to Approved.
- Log in as HOD → sees department items, can move Submitted → Finance review.
- Log in as Finance → can set GL code and move to Approved; can edit the Budget Envelope.
- Confirm deleting the app leaves all records intact (permissions are portal-side).
