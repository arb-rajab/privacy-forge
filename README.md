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

**0. One-time bootstrap.** There is no seeder yet (tracked as R-02 in
[`docs/project-memory/10-risk-register.md`](docs/project-memory/10-risk-register.md)) —
a fresh instance has no consent purpose, no active ABAC policies, and no
staff users. Run this once:

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

$verifier = \App\Models\User::create(['name' => 'Admin One', 'email' => 'admin1@example.test', 'password' => bcrypt('password'), 'role' => 'owner', 'active' => true]);
$approver = \App\Models\User::create(['name' => 'Admin Two', 'email' => 'admin2@example.test', 'password' => bcrypt('password'), 'role' => 'owner', 'active' => true]);

echo "purpose id: {$purpose->id}\n";
```

Copy the printed purpose id — you'll need it in step 1. `exit` tinker
when done.

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

**3. Act as your own admin.** There's no staff login page yet (a gap
this session surfaced — see
[`docs/project-memory/12-session-handoff.md`](docs/project-memory/12-session-handoff.md)),
so verifying identity and approving erasure — normally a staff member's
job — is done here directly against the same authorisation-gated
controllers a login-backed session would call, via tinker:

```bash
docker compose exec app php artisan tinker
```

```php
$dsar = \App\Models\DsarRequest::latest()->first();
$verifier = \App\Models\User::where('email', 'admin1@example.test')->first();
$approver = \App\Models\User::where('email', 'admin2@example.test')->first();
$request = \Illuminate\Http\Request::create('/');

$request->setUserResolver(fn () => $verifier);
app(\App\Http\Controllers\Admin\DsarController::class)->verifyIdentity($request, $dsar->id);

\Illuminate\Support\Facades\Http::fake(['example.test/*' => \Illuminate\Support\Facades\Http::response('', 200)]);
$request->setUserResolver(fn () => $approver);
app(\App\Http\Controllers\Admin\DsarController::class)->approveErasure($request, $dsar->id);

$task = \App\Models\DsarConnectorTask::where('dsar_request_id', $dsar->id)->first();
$connector = $task->connector;
$signer = app(\App\Services\ConnectorSignatureService::class);
$timestamp = (string) now()->timestamp;
$body = json_encode(['status' => 'success']);
$callback = \Illuminate\Http\Request::create("/api/v1/connector-callback/{$task->id}", 'POST', ['status' => 'success']);
$callback->headers->set('X-Connector-Signature', $signer->sign($connector->secret_hash, $timestamp, $body));
$callback->headers->set('X-Connector-Timestamp', $timestamp);
app(\App\Http\Controllers\ConnectorCallbackController::class)->handle($callback, $task->id);
```

**4. Check completion.** Reload your bookmarked status page from step 2
— it now shows `complete` and the deletion certificate.

**5. Withdraw.** Go back to the widget page from step 1 (or reload it —
it remembers your consent via `localStorage`) and click **Withdraw
consent**.

Steps 1, 2, 4, and 5 are exactly what a real visitor and a real
self-hoster would do in a browser; step 3's tinker snippet is a
documented stand-in for the staff login UI this project doesn't have
yet, calling the identical `PolicyEvaluator`-gated, audit-logged
controller code a real session would.

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
