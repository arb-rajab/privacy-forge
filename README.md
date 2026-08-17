# privacy-forge

> **Status:** 🚧 Session 13 — consent capture, DSAR lifecycle, retention,
> RoPA export, and ABAC are all implemented and tested; the embeddable
> consent widget and public DSAR portal (this session) close the last
> UI gap for a self-hostable v1. Not yet tagged v1.0.0 — see
> [`docs/project-memory/12-session-handoff.md`](docs/project-memory/12-session-handoff.md)
> for exactly what remains.

A self-hostable consent, data-subject-request (DSAR), and data-retention
engine for small SaaS teams who need a defensible answer to "prove you handle
personal data lawfully" — without an enterprise compliance-platform budget.

## What this demonstrates

- **Requirements Analysis (deep):** full regulatory traceability from GDPR
  article → requirement → test.
- **Retirement, Handover & Disposal (deep):** data export, retention, and
  deletion aren't an afterthought — they're the product.
- Attribute-based access control (ABAC), OWASP ASVS L2 mapping, tamper-evident
  audit logging.

Stack: Laravel 11 · Vue 3 (Inertia) · PostgreSQL · Redis · S3-compatible
storage.

## Project status

This repository is built through a session-based workflow. Current phase:
**Session 5 (Environment, Standards, CI Baseline) — complete.** Next:
Session 6 (Feature Implementation — first vertical slice).

Full portfolio context: this is a flagship repository in a broader
public/private software portfolio. See `docs/project-memory/` for the
complete project memory pack, and `docs/SDLC-EVIDENCE.md` (populated at
Session 9) for the phase-by-phase evidence map.

## Quickstart

```bash
git clone https://github.com/arb-rajab/privacy-forge.git
cd privacy-forge
cp .env.example .env
docker compose up --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Then visit `http://localhost:8000`. See `CONTRIBUTING.md` for the full
development workflow, including running tests, lint, and static analysis.

## Try it: consent → withdrawal → DSAR → export/erasure, in under 15 minutes

This is the walkthrough behind Success Metric #1 in
[`docs/project-memory/00-project-brief.md`](docs/project-memory/00-project-brief.md):
a stranger can self-host this and complete a full cycle starting from
this README alone. It assumes the Quickstart above is already done
(containers healthy, migrations run).

**0. One-time bootstrap.** A fresh instance has no active ABAC policies
(R-02, formerly in
[`docs/project-memory/10-risk-register.md`](docs/project-memory/10-risk-register.md),
closed this session) and no consent purpose/connector to try the widget
against. Run the real seeder and the reference-connector registration
command — no tinker, no shell access to application internals:

```bash
docker compose exec app php artisan db:seed
docker compose exec app php artisan connectors:register-reference
```

The seeder creates all five ABAC policies
(`dsar.identity.verify`, `dsar.erasure.approve`, `policy.update`,
`retention.policy.manage`, `ropa.export`) so staff actions aren't
fail-closed-denied by default. The second command registers a real
connector pointed at this application's own built-in reference/stub
webhook receiver (R-06, see step 4) and prints a one-time shared secret
— you don't need to record it for this walkthrough, only for a real
integration.

One consent purpose still needs creating — this is genuine demo content
(what a real self-hoster would configure through their own product,
not a bootstrap gap), so it stays a tinker step:

```bash
docker compose exec app php artisan tinker
```

```php
$purpose = \App\Models\ConsentPurpose::create(['name' => 'Newsletter', 'lawful_basis' => 'consent', 'status' => 'active', 'version' => 1]);
$notice = \App\Models\ConsentNotice::create(['purpose_id' => $purpose->id, 'version' => 1, 'body' => 'We will email you our newsletter. You can withdraw at any time.', 'published_at' => now()]);
$purpose->update(['current_notice_id' => $notice->id]);

echo "purpose id: {$purpose->id}\n";
```

Copy the printed purpose id — you'll need it in step 1. `exit` tinker
when done.

**Staff accounts, unlike the above, do not need tinker at all** (R-05 in
`10-risk-register.md`, closed this session — real staff login now exists).
Create your first Owner account with a dedicated artisan command instead:

```bash
docker compose exec app php artisan privacy-forge:create-owner --name="Admin One" --email=admin1@example.test --password=a-real-password
docker compose exec app php artisan privacy-forge:create-owner --name="Admin Two" --email=admin2@example.test --password=a-real-password
```

(Two accounts, because step 3 below needs a *different* admin to approve
erasure than the one who verified identity — ADR-0007's
separation-of-duties.)

**1. Give consent, as the widget's visitor.** Visit
`http://localhost:8000/embed-example.html?purposeId=<purpose id>` — a
plain static HTML page (not part of this app's own admin UI) standing in
for a third-party site that embedded the widget via
`<script src="/widget.js">`. Enter an email and click **I agree**.

**2. Submit a DSAR.** Visit `http://localhost:8000/dsar`, choose
**Erasure**, enter the *same* email, and submit. You'll be redirected to
a status page — bookmark that URL; it's the only way back in (no
account, per `docs/project-memory/05-api-contracts.md`'s auth model).
It will show `pending_verification`.

**3. Act as your own admin — for real, buttons only.** Visit
`http://localhost:8000/login` and log in as `admin1@example.test`
(**Admin One**, the identity verifier). From `/`, click **DSAR queue**,
find your request, and click **Verify identity**. Its status changes to
`in_progress` right there on the page.

Then click **Log out**, log back in as `admin2@example.test` (**Admin
Two**) — ADR-0007 requires a *different* admin to approve erasure than
the one who verified identity — go back to **DSAR queue**, and click
**Approve erasure**. (If you try this step as Admin One instead, the
button is still there, but the page shows the real ABAC denial message
instead of succeeding — that's the separation-of-duties policy working
as intended, not a bug.)

**4. Check completion.** Reload your bookmarked status page from step 2.
The reference connector registered in step 0 has a real, built-in
webhook receiver (`App\Http\Controllers\ReferenceConnectorWebhookController`,
this session's R-06 fix) — it genuinely receives the signed webhook
`DispatchConnectorTaskJob` sends and genuinely calls back with its own
signed response, so the status reaches **`complete`**, with a deletion
certificate attached (FR-011), usually within a few seconds and reliably
within well under a minute (a real self-hoster's first attempt sometimes
sees one delivery retry before success — ADR-0004's own retry/backoff
handling this correctly, not a bug). ADR-0004's real
connectors are still meant to be external, third-party-operated
services in production — this reference/stub is what proves the
contract works at all, which is exactly what a fresh instance's first
erasure needs to demonstrate. If you registered a *different* connector
of your own instead, pointed at a `webhook_url` nothing answers, you'll
see the same honest `partially_complete` outcome this README described
before this session — that failure path is correct behaviour, not a
bug, per FR-009.

**5. Withdraw.** Go back to the widget page from step 1 (or reload it —
it remembers your consent via `localStorage`) and click **Withdraw
consent**.

Every step above is now something a real visitor and a real self-hoster
do through a real browser session, buttons only — no tinker, no shell
access, no DevTools console, anywhere in this walkthrough. (Step 0's
consent-purpose bootstrap is the one remaining tinker step — genuine
demo content, not a missing seeder; the policy/connector bootstrap that
used to require tinker too is now two real commands, `php artisan
db:seed` and `php artisan connectors:register-reference`.)

## Documentation

- [`docs/project-memory/`](docs/project-memory/) — brief, requirements,
  architecture, security, testing, operations, decisions, risks, backlog,
  handoff, release notes, maintenance/retirement plan
- [`docs/adr/`](docs/adr/) — architecture decision records
- [`SECURITY.md`](SECURITY.md) — vulnerability disclosure policy

## Non-goals

See `docs/project-memory/01-scope-and-non-goals.md` (produced in Session 1).

## Licence

AGPL-3.0 — see [`LICENSE`](LICENSE). Rationale: this is a hostable
application, not a library; AGPL ensures modifications to a hosted version
remain shareable.
