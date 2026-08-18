# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 24 — revise the demo-deployment
  decision to descope real infrastructure; prove the deployment
  automation locally against placeholder values instead.**
- Objective: Part A — record, explicitly and not silently, that this
  portfolio build descopes actually provisioning and paying for real
  public infrastructure for the demo instance (revising Session 1's
  original decision). Part B — prove the deployment automation and every
  Demo Instance Data Safety control that doesn't itself require real
  infrastructure, end to end, against a placeholder domain and
  self-signed TLS, at the same standard of proof Deployment Session A
  (Session 23) used for plain HTTP.
- Status: **Both parts complete.** The decision is recorded in
  `09-decision-log.md` with full reasoning, before any other work this
  session touched. The full production-shape stack was built, run, and
  verified end to end over genuinely-validated HTTPS against
  `demo.privacy-forge.example` (a deliberately fake, RFC 2606-reserved
  domain) with `tls internal` TLS. All four applicable Demo Instance Data
  Safety controls were exercised and confirmed working against this real
  running deployment; the fifth (infrastructure isolation/spend cap) is
  explicitly marked not-applicable, not silently skipped. `B-07` and
  `B-08` are both resolved. 187 Feature tests pass (186 existing + 1 new
  for the `demo:reset` behaviour change), Pint/Larastan (level 8)/ESLint
  clean, OpenAPI validates.

## Part A: the decision, recorded before any other work

`09-decision-log.md`'s "Demo/hosting decision revised" entry states
plainly: real cloud provisioning is out of scope for this portfolio
build. This is not a funded product — an indefinitely-live public
instance requires ongoing real cloud spend with no revenue behind it,
disproportionate to the credibility gain over a rigorously proven local
deployment. Deployment readiness is instead demonstrated via a fully
worked local/simulated deployment against placeholder infrastructure
values (a fake domain, self-signed TLS), proving the automation would
work identically against real infrastructure without actually paying for
or exposing one.

**What this does NOT change, stated explicitly:** the Demo Instance Data
Safety CODE controls already built — scheduled reset (`demo:reset`),
connector registration genuinely compiled out (no HTTP registration
endpoint anywhere), the warning banner (`demoMode` shared Inertia prop),
and the `DEMO_MODE` flag itself — remain real, tested designs, unchanged
by this decision. This session proves all four work; it does not weaken
or remove any of them. Only the infrastructure-isolation/spend-cap
control and the underlying "is this live and public" question are
affected, and both are marked explicitly not-applicable, not silently
dropped.

**What this does NOT reopen:** GDPR/UK-GDPR-only scope, single-tenancy
(ADR-0005), and every other ADR are untouched. This is a revision to
exactly one Session 1 business assumption (the demo-hosting decision)
and its downstream Success Metric #5, both updated in
`00-project-brief.md` with the revision stated plainly, not silently
applied — matching the discipline Session 18 used revising Success
Metric #1.

## Part B: built and verified against placeholder infrastructure

- **`docker/Caddyfile`** now serves `demo.privacy-forge.example`
  (deliberately fake, RFC 2606-reserved) with `tls internal` — Caddy's
  own offline local-CA issuance, substituting for real ACME/Let's
  Encrypt (which needs a real, resolvable domain and a real challenge,
  neither of which exist here). The Caddyfile's own comment states the
  load-bearing point: swapping the site address for a real domain and
  deleting the `tls internal` line is the *entire* diff needed to run
  this exact config against real infrastructure. A second, unrelated
  plain-`:80` block keeps the existing internal service-to-service
  traffic (the reference connector's own webhook callback loop) working
  exactly as Session 23 left it — Caddy's automatic-HTTPS redirect is
  host-matched, so it doesn't interfere.
- **`docker-compose.prod.yml`**: `web` now also publishes `8443:443`
  (a local-only artifact) and a `caddy-data` volume (so the internal CA
  survives a restart); `APP_URL` reads `https://demo.privacy-forge.example`.
- **`config/demo.php` + `.env.example`**: new `DEMO_VIEWER_EMAIL`/
  `DEMO_VIEWER_PASSWORD` (`B-08`).
- **`App\Console\Commands\ResetDemoInstanceCommand`**: now truncates
  `users` too (added to the existing single-statement `TRUNCATE`, since
  `audit_log_entries`/`dsar_requests` carry live foreign keys into it)
  and re-creates exactly one fixed, documented demo-viewer account
  (role `owner`) every time it runs.
- **`tests/Feature/ResetDemoInstanceCommandTest.php`**: updated — the
  old "users are deliberately untouched" assertion is now the opposite
  (a pre-existing user, including a stale demo-viewer row with the wrong
  role/password, does not survive a reset); one new test covers a reset
  correctly overwriting a stale leftover demo-viewer account.

### A real bug found while proving this

`opcache.validate_timestamps=0` (intentionally set on this production
image) means PHP-FPM's already-running worker processes keep executing
their previously-compiled `bootstrap/cache/config.php` even after the
file on disk changes — so a one-off `docker compose exec -e
DEMO_MODE=true ... php artisan config:cache` against an already-running
container looked like it worked (a fresh CLI process reflected
`config('demo.enabled') === true`) but the live HTTP path still served
`demoMode: false`, because the long-lived PHP-FPM workers never noticed
the file changed. Fix: set `DEMO_MODE=true` as a real container
environment variable and recreate the container
(`--force-recreate`), letting `entrypoint.prod.sh`'s own `config:cache`
run fresh from process start — the correct way an operator would
actually do this, not a workaround. Full account in
`09-decision-log.md`'s Session 24 entry.

### Verified for real, over real HTTPS, with a genuinely validated certificate chain

1. `docker compose -f docker-compose.prod.yml -p privacy-forge-prod up
   -d --build` from a clean state (prior stack and volumes removed
   first) — both images build clean, `web` reports healthy.
2. Caddy's internal root CA extracted directly from the running
   container and used as `curl --cacert` (not `-k`) — the same trust
   decision a real client makes against a real CA.
3. `GET /up` over `https://demo.privacy-forge.example:8443` → `200`,
   chain validated, leaf certificate SAN confirmed to be exactly the
   placeholder domain.
4. A plain HTTP request with `Host: demo.privacy-forge.example` → real
   `308 Permanent Redirect` to HTTPS — Caddy's automatic-HTTPS redirect,
   live and working.
5. Real migrations, real `demo:reset`, a real `POST /login` over HTTPS
   with a genuine session/CSRF cookie flow (not a bypassed session) using
   the fixed demo-viewer credentials, then a real authenticated `GET
   /api/v1/admin/audit-log` → `200`, returning that exact login's own
   `audit.log.view` audit entry.
6. `demo:reset`'s reset behaviour re-verified against real data, not
   just "the command exits 0": a consent purpose created via `tinker`
   existed before a reset and was confirmed gone after it.
7. Connector registration re-confirmed genuinely compiled out on the
   *running* production image (`php artisan route:list` inside the live
   container) — exactly two connector-related routes exist (the
   reference connector's own webhook receiver, and the generic
   connector-callback endpoint), no registration endpoint anywhere.

### Go/no-go checklist (`08-deployment-and-operations.md`), exercised item by item

`DEMO_MODE=true` (real container env, confirmed via the live `demoMode`
prop) — done. Connector registration compiled out — confirmed on the
running image. A real spend cap and infrastructure isolation —
**explicitly marked not applicable**, per the Part A decision, not
silently skipped. `demo:reset` scheduled and proven to actually reset
real state — done (never run by an actual long-lived cron scheduler
against a persistent instance, since none exists, by decision — an
honest, stated limit). `B-08` — resolved. `B-07` — resolved by decision.

### B-07/B-08 resolution

- **`B-07`** (richer synthetic demo content): **resolved by decision,
  not built out.** The existing minimal baseline is sufficient for this
  project's actual remaining purpose (proving the mechanics work); a
  compelling dataset for a real visitor is downgraded to non-blocking
  backlog polish, since there is no real visitor to be compelling to.
- **`B-08`** (per-visitor demo identity): **closed, actually built.** A
  single fixed, documented demo-viewer credential, re-created by every
  `demo:reset`. Normally the wrong answer to Demo Instance Data Safety
  control 2 / T-19 (a fixed credential facing the real public internet
  is a real abuse vector) — accepted here **only** because Part A's
  decision also means there is no real public internet this instance is
  reachable from. Recorded as a conditional simplification: if this
  project is ever actually deployed publicly for real, this must be
  revisited first, not carried forward unexamined. `config/demo.php`,
  `.env.example`, `06-security-threat-model.md`'s T-19 row, and
  `11-backlog.md` all state this caveat explicitly.

## MVP boundary checklist and "credible v1 complete" — stated plainly

**`01-scope-and-non-goals.md`'s MVP boundary checklist is now 9 of 9
complete** — the ninth item (public demo instance) is closed by the
explicit descoping decision above, not by quietly redefining what it
asks for. What it actually asked for — synthetic seed data, a working
demo-safety posture, infrastructure isolation/spend cap — is honestly
satisfied: the first two are genuinely true; the third is explicitly
marked not-applicable under the revised scope, which is a different,
honest thing from "still missing."

**This does not mean v1.0.0 can be tagged.** `01-scope-and-non-goals.md`'s
own "Definition of v1 complete" has four independent conditions:

1. **Every MVP boundary box checked and demonstrably working
   end-to-end.** ✅ Met, as of this session, for the reasons above.
2. **All five success metrics met and verifiable by a third party.**
   ✅ Metrics 1-4 unaffected and previously confirmed; Metric 5 is
   reworded this session to match the revised scope and remains
   third-party-verifiable (a reviewer can run the exact same
   local/placeholder proof from this repository alone, no special
   credentials needed).
3. **The Gate 9→10 checklist in the session system
   (`04-session-system-and-templates.md`) passes: README quickstart
   verified on a clean machine, diagrams current, demo available, SDLC
   evidence map complete, case study published.** ❌ **Not met, and a
   newly found gap this session: `04-session-system-and-templates.md`,
   the file `01-scope-and-non-goals.md` itself cites for this checklist,
   does not exist anywhere in this repository or its git history**
   (checked directly: `git log --all --diff-filter=A` for that filename
   returns nothing — it was never created, not deleted). This is the
   same shape of finding as `B-06`'s Session-5 forward-looking
   placeholder (Session 23's re-verification) — a citation to something
   that was apparently planned but never actually written — flagged here
   rather than silently left for a future session to trip over.
   Independent of that gap, this session did not verify a README
   quickstart on a genuinely clean machine, does not know the diagrams'
   current status, and no case study has been published — this condition
   is honestly unmet on its merits, not merely blocked by the missing
   file.
4. **No non-goal has silently crept back into scope.** ✅ Unaffected;
   nothing in this session touches the non-goals table.

**Verdict, stated plainly: privacy-forge is not yet credible v1.0.0-
taggable.** The specific gap this session was asked to resolve (the live
public demo instance) is genuinely closed — by an honest, explicit
descoping decision, not left undone and not quietly hand-waved. The
remaining blocker to "v1 complete" is condition 3, which this session
did not attempt and which has its own newly-discovered documentation gap
(the missing session-system file) on top of the substantive unmet items
(README quickstart re-verification, diagrams, case study). This is a
different, smaller remaining gap than the one this project has carried
for the last several sessions, and it is now the honest bottleneck.

## What was explicitly NOT done this session, and why

1. **No real infrastructure was provisioned, no domain was registered,
   no money was spent.** This is not an oversight — it is the explicit
   point of Part A's decision.
2. **`04-session-system-and-templates.md`'s Gate 9→10 checklist was not
   attempted** — out of this session's scope (Part A/B were about the
   demo-deployment decision specifically), flagged as a finding rather
   than silently worked around.
3. **No ADR opened or reopened.** GDPR-only, single-tenant, and every
   existing ADR untouched.
4. **R-01 through R-08 — not touched, none affected.** No unrelated
   application code changed this session beyond `ResetDemoInstanceCommand`
   and its test (both already in scope of B-08).
5. **B-01 through B-05 — unchanged, still open.** Out of scope.

## Files created or changed

**Changed:**
- `docker/Caddyfile` — placeholder domain + `tls internal`, plus an
  unrelated plain-HTTP catch-all for internal service traffic.
- `docker-compose.prod.yml` — `web`'s `8443:443` port, `caddy-data`
  volume, `APP_URL` now the placeholder HTTPS domain.
- `config/demo.php` — `viewer_email`/`viewer_password` (`B-08`).
- `.env.example` — `DEMO_VIEWER_EMAIL`/`DEMO_VIEWER_PASSWORD`.
- `app/Console/Commands/ResetDemoInstanceCommand.php` — truncates
  `users`, re-creates the fixed demo-viewer account; class comment
  rewritten to match.
- `tests/Feature/ResetDemoInstanceCommandTest.php` — updated assertions
  for the new `users`-truncation behaviour; one new test.
- `docs/project-memory/00-project-brief.md` — Demo/hosting decision and
  Success Metric #5 revised.
- `docs/project-memory/01-scope-and-non-goals.md` — MVP checklist item
  9 closed by decision; "v1 complete" assessment pointer added.
- `docs/project-memory/06-security-threat-model.md` — Demo Instance
  Data Safety implementation-status table and T-19/T-20 rows updated.
- `docs/project-memory/08-deployment-and-operations.md` — Environments,
  build/release, deployment procedure, configuration/secrets,
  observability, runbooks, backup/restore, capacity/cost, and the full
  Sessions A/B/C plan rewritten to reflect the descoping decision and
  this session's verification.
- `docs/project-memory/09-decision-log.md` — three new Session 24
  entries (the descoping decision; B-07/B-08 resolution; the local
  placeholder-TLS deployment proof account).
- `docs/project-memory/11-backlog.md` — `B-07`/`B-08` closed; `B-06`'s
  remaining "infra half" reclassified from open to descoped.
- `docs/project-memory/12-session-handoff.md` (this file).

**Not changed:** any ADR, GDPR-only/single-tenant decisions, `R-01`
through `R-08`, `B-01` through `B-05`, any other application PHP/JS
source, `composer.json`/`composer.lock`/`package.json` (no new
dependencies).

## Validation performed

- **`composer test` (Pest, dev stack) → 187/187 passed** (186 + 1 new
  for the `demo:reset` behaviour change), re-run after the code change.
- **`composer lint` (Pint) → clean, 161 files.**
- **`composer analyse` (Larastan, level 8) → 0 errors, 68 files.**
- **`npm run lint` (ESLint) → clean.**
- **`docs/architecture/openapi.yaml` → valid**, same throwaway
  `python:3.12-slim`-container method prior sessions used.
- **Production stack, real HTTPS walkthrough** — see "Verified for real,
  over real HTTPS" above; the rigorous evidence this session adds beyond
  the existing Pest suite.
- **Dev stack left in a working state**, unaffected by this session's
  Docker/doc/single-command changes; the production stack
  (`docker-compose.prod.yml`, project name `privacy-forge-prod`) is left
  running locally with `DEMO_MODE=true` and the fixed demo-viewer
  account, as the tangible artifact this session's verification
  produced.

## Open questions and risks

- **R-01 through R-08 — not touched, none affected.**
  - R-07's rate-limit follow-up (due 2026-08-24) is still not due today
    (2026-08-18).
  - R-08 — unchanged, accepted residual risk.
- **B-01, B-02, B-03 — unchanged, still open.**
- **B-04, B-05 — closed (Session 22), unaffected this session.**
- **B-06 — fully closed this session** (image half: Session 23; infra
  half: descoped by decision, Session 24).
- **B-07, B-08 — closed this session**, both by the reasoning above.
- **MVP boundary — 9 of 9.** **"v1 complete" — not yet**, blocked on
  condition 3 (Gate 9→10 checklist), which has its own newly-found
  documentation gap (the missing `04-session-system-and-templates.md`).

## Next recommended session

**The demo-deployment question is closed for this portfolio build — do
not reopen it** (short of the user explicitly deciding to actually fund
and provision real infrastructure, which would need its own new
decision, not a quiet reversal of this one). The genuine remaining
bottleneck to "v1 complete" is condition 3 of
`01-scope-and-non-goals.md`'s own definition:

1. **Resolve the `04-session-system-and-templates.md` gap first** —
   either locate/reconstruct what that file was supposed to contain (if
   it exists in some other form, e.g. under a different filename) or
   author it for real, since `01-scope-and-non-goals.md` cites a
   specific Gate 9→10 checklist that currently has no home.
2. **Run that checklist for real**: a genuinely clean-machine README
   quickstart verification, a check of whether the architecture diagrams
   are current, confirmation that `docs/SDLC-EVIDENCE.md` is complete,
   and a decision on what "demo available" now honestly means given this
   session's descoping (almost certainly: point at the local/placeholder
   proof, not a live URL) and whether a case study has been or needs to
   be published.
3. Only after that should v1.0.0 tagging be considered.

- Inputs required: `docs/project-memory/01-scope-and-non-goals.md`
  (updated this session — read the "Definition of v1 complete" section
  and its pointer to `04-session-system-and-templates.md`),
  `docs/project-memory/09-decision-log.md`'s Session 24 entries,
  `docs/project-memory/08-deployment-and-operations.md` (the closed
  Sessions A/B/C account).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 24
complete.

**Current stack:** unchanged — no dependency versions touched this
session.

**Architecture decisions that must not be reversed:** all ADRs (0001-
0008), GDPR-only, single-tenant. **One decision explicitly revised this
session, on the record:** the demo instance will not be a real, live,
publicly-hosted deployment for this portfolio build — see
`09-decision-log.md`'s Session 24 "Demo/hosting decision revised" entry.

**Implementation state:**
- Done: everything from Session 23, plus: placeholder-domain HTTPS
  (self-signed via Caddy `tls internal`), a fixed demo-viewer credential
  (`B-08`), `B-07` resolved by decision, and the full go/no-go checklist
  exercised against this local deployment.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) `04-session-system-and-templates.md`
  — cited by `01-scope-and-non-goals.md` but does not exist anywhere in
  this repository, newly found this session; (2) the Gate 9→10 checklist
  itself (README quickstart, diagrams, SDLC evidence map, case study) —
  not attempted; (3) R-01 — still open; (4) R-07's rate-limit follow-up
  — due 2026-08-24; (5) R-08 — accepted residual, unchanged; (6)
  B-01/B-02/B-03 — unchanged, still open.
- Not started: `04-session-system-and-templates.md` and the Gate 9→10
  checklist it's supposed to define.

**Constraints and non-goals:** unchanged since Session 1, except the
demo-hosting revision above. Still at the 2-new-technology cap (ABAC,
ASVS L2) — Caddy remains infrastructure/ops tooling, not a new
architectural pattern.

**Task for next session (single objective):** resolve the
`04-session-system-and-templates.md` gap and run the Gate 9→10 checklist
for real — this is now the actual remaining blocker to "v1 complete,"
not the demo instance.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/01-scope-and-non-goals.md` (updated — read the
  "Definition of v1 complete" section)
- `docs/project-memory/09-decision-log.md`'s Session 24 entries

**Ground rules:** Do not reopen the demo-hosting decision without the
user explicitly asking to actually fund and provision real
infrastructure. Do not reopen any ADR. Do not reopen GDPR-only/
single-tenant. R-01 remains open; R-07's follow-up isn't due until
2026-08-24; R-08 is accepted residual — don't reopen any of them without
a genuine new finding.
