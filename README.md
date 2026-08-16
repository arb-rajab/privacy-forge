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

**0. One-time bootstrap.** There is no seeder yet for consent
purposes/policies/connectors (tracked as R-02 in
[`docs/project-memory/10-risk-register.md`](docs/project-memory/10-risk-register.md)) —
a fresh instance has none of those. Run this once:

```bash
docker compose exec app php artisan tinker
```

```php
$purpose = \App\Models\ConsentPurpose::create(['name' => 'Newsletter', 'lawful_basis' => 'consent', 'status' => 'active', 'version' => 1]);
$notice = \App\Models\ConsentNotice::create(['purpose_id' => $purpose->id, 'version' => 1, 'body' => 'We will email you our newsletter. You can withdraw at any time.', 'published_at' => now()]);
$purpose->update(['current_notice_id' => $notice->id]);

\App\Models\PolicyDefinition::create(['action_name' => 'dsar.identity.verify', 'version' => 1, 'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']]], 'resource_conditions' => [], 'environment_conditions' => [], 'effect' => 'allow', 'status' => 'active']);
\App\Models\PolicyDefinition::create(['action_name' => 'dsar.erasure.approve', 'version' => 1, 'subject_conditions' => ['role' => ['in' => ['owner', 'privacy_manager']], 'id' => ['not_equals_attribute' => 'resource.identity_verified_by']], 'resource_conditions' => ['status' => ['in' => ['in_progress']], 'request_type' => ['in' => ['erasure']]], 'environment_conditions' => [], 'effect' => 'allow', 'status' => 'active']);
\App\Models\Connector::create(['name' => 'Demo Connector', 'webhook_url' => 'https://example.test/webhook', 'secret_hash' => str()->random(40), 'status' => 'active', 'registered_at' => now()]);

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

**3. Act as your own admin — for real, no shell access.** Visit
`http://localhost:8000/login` and log in as `admin1@example.test`
(**Admin One**, the identity verifier). There is no admin dashboard yet
with dedicated verify/approve buttons (`01-scope-and-non-goals.md` still
lists "a richer admin dashboard" as backlog), so those two actions are
called directly against the JSON API a real admin client would call —
but authenticated by the real, logged-in browser session you just
created. With the same browser tab open, open its DevTools console
(F12) and paste, substituting the DSAR id from your bookmarked status
page's URL:

```js
const csrfToken = JSON.parse(document.getElementById('app').dataset.page).props.csrfToken;
await fetch('/api/v1/admin/dsar/<dsar-id>/verify-identity', {
  method: 'POST',
  headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
});
```

Then log out (click **Log out** on the `/` page) and log back in as
`admin2@example.test` (**Admin Two**) — ADR-0007 requires a *different*
admin to approve erasure than the one who verified identity — and paste
the same snippet again with `approve-erasure` in place of
`verify-identity`.

**4. Check completion.** Reload your bookmarked status page from step 2
— it now shows `complete` and the deletion certificate.

**5. Withdraw.** Go back to the widget page from step 1 (or reload it —
it remembers your consent via `localStorage`) and click **Withdraw
consent**.

Every step above, including step 3, is now something a real visitor and
a real self-hoster do through a real browser session — no tinker, no
shell access to application code anywhere in this walkthrough. The one
remaining rough edge is that verify-identity/approve-erasure have no
dedicated buttons yet (no admin dashboard), so step 3 calls the JSON API
directly rather than clicking through a page — but the *authentication*
behind that call is the real thing: `POST /login`, a real session
cookie, real CSRF, real rate-limited attempts (see
`docs/project-memory/10-risk-register.md`, R-05).

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
