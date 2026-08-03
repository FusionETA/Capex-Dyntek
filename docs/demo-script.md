# Capex Management System — Customer Demo Script

A ~12–15 minute walkthrough that tells one story: **a capex request goes from
raised → approved → reflected in the sales target**, all inside Bitrix24, with the
right people seeing the right things.

---

## Before the demo (5-min prep)

**Log in to Bitrix24 as the account mapped to Group CFO** (in the test portal that's
**Rachel**). Group CFO can do *everything* — submit, approve, edit targets, manage
access — so you can run the entire happy path from **one login** without switching users.

Have ready:
- The **Capex** app open in Bitrix (left menu → **Capex**), on the **Dashboard**.
- A second browser/account signed in as a **non-approver** (e.g. an HOD, or a user with
  *no* role) if you want to show access control live. Optional — you can also just
  *describe* it.
- Know one number you'll type: a request amount, e.g. **40,000**.

**One-line framing to open with:**
> "This is a single place, inside Bitrix, where staff raise capital-expenditure requests,
> the right manager approves them by amount, and Finance keeps the sales targets up to
> date — no spreadsheets, no email chains."

---

## Act 1 — The dashboard (visibility) · ~2 min

**Show:** the **Dashboard**.

**Say:**
- "Everyone who's given access lands here. Three numbers up top: **approved capex this
  year**, **how many are pending approval**, and **regions tracked**."
- Point to **Sales targets**: "Per region — the corporate target, the revised target, and
  what's actually been met, with a progress bar."
- Point to **Top approved capex**: "The approved requests, ranked by value, visible to
  everyone — so there's one shared view of what's been committed."

**Talking point:** "This is real data from your planning sheet — SG, HK, MY, ID — loaded in."

---

## Act 2 — Raise a request (the requester) · ~2 min

**Show:** click **+ New request**.

**Do:** fill it in as you talk:
- Title: *"Warehouse racking upgrade"*
- Region: **MY**, Category: **Plant & machinery**
- Amount: **40,000**, Currency: **SGD**
- PIC: a name, Timeline: **2026/Q3**
- Justification: one line.
- Click **Submit request**.

**Say:**
- "The moment it's submitted, the system converts the amount to SGD and **routes it to the
  right approver based on the amount** — this one's under S$50k, so it goes to the **HOD**."
- Read the confirmation aloud: *"…routed to the HOD for approval."*

**Talking point:** "The requester never has to know who approves — the rules decide."

---

## Act 3 — Approve it (the authority) · ~3 min

**Show:** click **Approvals**.

**Say:**
- "An approver only sees the requests **they** can decide. Here's the one we just raised."
- Click **Approve**.
- "Done — it moves to **Approved** and now shows on the dashboard ranking."

**Show the guardrail (the memorable moment):**
- "Now watch what happens with a **big** one." (If you have a large pending request, or
  raise a S$2,000,000 one first.) "As an HOD, I can't approve this — it's over my S$50k
  limit, so the system **refuses** it and it stays put. It would need the Group CFO."

**Talking point:** "Delegation of authority is enforced, not just documented — nobody can
approve above their band."

*(Bands: HOD ≤ S$50k · Regional Finance ≤ S$250k · Country MD ≤ S$1m · Group CFO any.)*

---

## Act 4 — Update the sales target (Finance / Carol) · ~2 min

**Show:** click **Sales Targets**.

**Say:**
- "Once capex is approved, Finance updates the region's numbers here. **Carol types in**
  the new target and what's currently been met — nothing is auto-calculated, so the
  figures are exactly what Finance signs off."
- Change **MY**'s *Current met* to a new value, click **Save**.
- Flip back to **Dashboard** — "and it's reflected instantly for everyone."

**Talking point:** "The dashboard everyone sees is always the latest Finance-entered truth."

---

## Act 5 — Access control (why this is safe) · ~3 min

**Say:** "Not everyone should see or do everything — so access is **closed by default**."

**Show:** click **Manage Access**.
- "Only Finance-lead and admins reach this screen. It lists **exactly who can use the app**
  and their role." Click the small **ⓘ** — "here's what each role can do."
- "To onboard someone, I pick them from the list and choose a role — **Grant access**.
  To change or remove, it's one click. No IT ticket, no config file."
- Mention the guardrail: "It won't let you lock everyone out — you can't remove the last
  administrator."

**Show access denial (optional, if you have a second account):**
- Open the app as a user with **no role** → "They get a clean **No access** screen."
- Or as an **HOD** → "No Manage-Access tab, no editing targets — they only see what their
  role allows."

**Talking point:** "Tabs are hidden *and* every action is re-checked on the server — so it's
genuinely locked down, not just hidden buttons."

---

## Act 6 — Under the hood: the Bitrix records (optional, technical audience) · ~2 min

Use this if the audience wants to see *where the data lives*. Skip for a purely
business audience.

**Say:** "Everything you've just seen is stored as **native Bitrix Smart Processes** —
the app doesn't have its own database. There are just **two record types**."

**Show — the Capex Request Smart Process** (CRM → Capex Request → **Kanban**):
- "Here's the same request we raised, sitting in the **Submitted** column. The stages are the
  workflow: **Draft → Submitted → Approved**, with **Rejected** off to the side."
- "When we approved it in the app, it moved to the **Approved** column here — same record,
  one source of truth. You can also drag it in the Kanban and the app stays in sync."
- Open the record: "Every field is here — **Request code, Region, Category, Amount (local),
  Amount (SGD), PIC, Timeline, Date of request, Date of approval, Justification**. Plus
  Bitrix's built-in **history and comments** on every record, for audit."

**Show — the Sales Target Smart Process** (CRM → Sales Target → **List**):
- "Four records, one per region — **Region, Period, New target (Target SGD), Current met
  (Actual SGD)**. This is exactly what Carol edits from the app's Sales Targets screen."

**The three points to land:**
1. **It's Bitrix data** — so you already know it: same permissions model, same history,
   same search, same exports.
2. **Two tables, not a sprawl** — Capex Request and Sales Target. That's the whole data model.
3. **Uninstall-safe** — if you ever remove the app, these records **stay** in Bitrix. The app
   is the workflow layer, not the storage.

**Talking point:** "So there's no lock-in and no shadow database — it's your Bitrix, with a
purpose-built capex workflow on top."

---

## Closing · ~1 min

Recap the value in the customer's terms:
- **One place** in Bitrix — no spreadsheets or email approvals.
- **Requests route themselves** to the right authority by amount.
- **Finance owns the sales targets**, and everyone sees one shared, current picture.
- **Access is controlled** and managed in-app by your own admins.
- "It's live and tested end-to-end on our test portal today; going live on your portal is
  the same install."

---

## Anticipated questions

| They ask… | You say… |
|---|---|
| "Does this replace Bitrix records?" | "No — it *is* Bitrix. The data lives in Bitrix Smart Processes; the app adds the workflow and screens. Uninstalling leaves the data intact." |
| "Can we change the approval amounts?" | "Yes — the bands are configurable per your delegation-of-authority policy." |
| "Who can submit?" | "You decide — we restrict it to your Tier 0–2 (MG–MS) staff; everyone else can be view-only or have no access." |
| "Do approvers get notified?" | "That's the next step — email + a Bitrix task when a request lands, with reminders. The approval itself already works." |
| "Multiple currencies?" | "Yes — amounts are entered in local currency and converted to SGD for a group-wide view." |
| "Is it secure?" | "Access is closed by default, every action is re-checked server-side, and identities are signed so they can't be spoofed." |

---

## Reset after the demo

If you created demo records, delete the test request(s) from the Bitrix **Capex Request**
Kanban, and set the **MY** sales-target figure back if you changed it. (Or ask us to reset
the test portal.)
