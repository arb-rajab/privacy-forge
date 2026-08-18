# Deployment and Operations
> Purpose: how this runs, and how someone else keeps it running
> Project: privacy-forge (public)
> Last updated: 2026-08-18 (Session 24 — real infrastructure explicitly
> descoped for this portfolio build; Sessions B/C executed locally
> against placeholder infrastructure values instead. Session 23 executed
> Session A for real (B-06's production image); Session 22 first gave
> this file real content, replacing the empty template through Session
> 21)

**Scope note.** This project has two genuinely different "deployments":
(1) a self-hoster's own instance, which is fully covered by the README
and `docker-compose.yml` and is *not* this project's operational
responsibility (per `14-maintenance-and-retirement.md` — "every
deployment is a self-hoster's own instance"); and (2) **the public demo
instance** (`00-project-brief.md`, Session 1's "Demo/hosting decision",
**revised Session 24** — see `09-decision-log.md`), which is what this
document is actually about. **As of Session 24, this project does not
run, and does not intend to run, a real live public instance** — that
half of the original decision is explicitly descoped for this portfolio
build (real cloud spend and a permanently-exposed public box were never
proportionate to a portfolio piece with no funded operations budget).
What this document now describes is a fully worked local/simulated
deployment against placeholder infrastructure values, proving the same
deployment automation and demo-safety controls that a real deployment
would need, without actually provisioning one. Nothing below applies to
a self-hoster's install.

## Environments

| Environment | Purpose | Status |
|---|---|---|
| Local dev | `docker-compose.yml` (`app`/`worker`/`postgres`/`redis`/`minio`/`frontend`) | Exists, used every session |
| CI | `.github/workflows/ci.yml` — Pint, Larastan, Pest, ESLint, CodeQL, `osv-scanner`, OpenAPI validation | Exists |
| Production-shape local proof | `docker-compose.prod.yml` + `docker/Dockerfile.prod` + `docker/Caddyfile`, run locally against a placeholder domain (`demo.privacy-forge.example`, self-signed TLS) | **Exists and is fully verified, Session 24** — build, migrate, seed, `DEMO_MODE=true`, real HTTPS termination, real login, real authenticated API call, `demo:reset` proven to genuinely reset state. This is the closest thing this project has to "the public demo instance," and per the Session 24 decision, it is deliberately the final form of it — not a rehearsal for a later real deployment that this project plans to do next. |
| A real, live, publicly-reachable instance | N/A | **Explicitly out of scope for this project, by decision (Session 24, `09-decision-log.md`).** No cloud account, VPS, DNS, or real TLS certificate exists or is planned. If a human with real cloud credentials ever wants to run this for real, the production-shape local proof above is the artifact to deploy — see "What a real deployment would still need" below. |

There is no staging environment and none is planned — a single-org,
self-hosted product has no shared production environment of its own to
stage changes against; the local production-shape proof above is a
reviewer-facing/portfolio-evidence artifact, not a staging tier for
anything.

## Build and release pipeline

- CI (`.github/workflows/ci.yml`) already runs on every PR/push to
  `main`: `php-quality` (Pint, Larastan level 8, Pest), `frontend`
  (ESLint), `security` (CodeQL, `osv-scanner`), `dependency-governance`
  (ADR-0008's safeguard), and OpenAPI spec validation. This is the
  release gate for the *codebase*; it says nothing about deployment,
  since nothing is deployed anywhere today.
- **`docker/Dockerfile`'s `runtime` target is a development image**
  (`php artisan serve`, Laravel's own docs call this "not intended for
  production") — this remains true and unchanged; that target was never
  meant to become the production image.
- **A real production-grade image now exists: `docker/Dockerfile.prod`**
  (built Session 23, closing `B-06`). Two targets, both built by
  `docker-compose.prod.yml`:
  - `app` — PHP-FPM 8.3 with OPcache enabled (production settings:
    `validate_timestamps=0`), application code, and compiled frontend
    assets baked in at build time (a `frontend-assets` stage runs `npm
    run build`). No Node/npm/Playwright, no `sockets`/`pcntl` (those are
    test-only, per decision log Session 13 — this image never runs
    `tests/Browser/`).
  - `web` — Caddy, serving `public/build`'s compiled static assets
    directly and reverse-proxying dynamic requests to `app` over
    FastCGI (`php_fastcgi`).
  - Verified locally, over real HTTP, against real Postgres/Redis/MinIO
    containers: build → migrate → seed → create a real Owner → real
    `POST /login` → real authenticated `GET /api/v1/admin/audit-log` →
    `200`. See `09-decision-log.md`'s Session 23 entry for the full
    account, including two real bugs found and fixed while building it
    (an npm/rollup musl issue on the original Alpine-based Node stage;
    a Caddy `php_fastcgi` root-path mismatch between the two
    containers' filesystems).
  - **Session 24: re-verified over real HTTPS instead, against a
    placeholder domain.** `docker/Caddyfile` now serves
    `demo.privacy-forge.example` (RFC 2606-reserved, deliberately fake)
    with `tls internal` (Caddy's own offline local-CA issuance,
    substituting for real ACME/Let's Encrypt — see the Caddyfile's own
    comment for why swapping this for a real domain is the entire diff
    needed). Verified with a genuinely validated certificate chain
    (Caddy's internal root CA extracted from the running container and
    used as `curl --cacert`, not `-k`/insecure-skipped), including the
    automatic HTTP→HTTPS redirect. Full account, including a real
    OPcache/`validate_timestamps=0` gotcha found while toggling
    `DEMO_MODE` on a running container, in `09-decision-log.md`'s
    Session 24 entry.
  - **By decision (Session 24, `09-decision-log.md`), this image is not
    intended to run anywhere but here.** Real cloud provisioning is
    explicitly out of scope for this portfolio build — this is not a
    "not yet proven" gap awaiting a future session, it is a deliberate,
    permanent scope boundary. See "What a real deployment would still
    need" below for what *would* be required if that decision is ever
    revisited.
  - `docker/Dockerfile`'s and `docker-compose.yml`'s header comments,
    which previously and incorrectly referenced a production image
    "built at Session 8," are corrected to point here.
- No image registry is configured; no CD pipeline exists; nothing
  publishes a build artifact to a registry anywhere today (the two
  images above are built locally, not pushed anywhere) — and, per the
  Session 24 decision, none is planned.

## Deployment procedure

**Status: this is the real, complete procedure for what this project
actually deploys — a local, placeholder-backed proof, not a stub
awaiting real infrastructure.** `docker compose -f docker-compose.prod.yml
up -d --build`, then `docker compose -f docker-compose.prod.yml exec app
php artisan migrate --force`, then (with `DEMO_MODE=true` set as a real
container environment variable, not patched into a running container —
see the OPcache finding in `09-decision-log.md`'s Session 24 entry for
why that distinction matters) `php artisan demo:reset` to seed the
baseline and the fixed demo-viewer account. Reachable at
`https://demo.privacy-forge.example:8443` locally (`--resolve` or a
`/etc/hosts` entry needed, since no real DNS exists for a fake domain).
See `09-decision-log.md`'s Session 23 and 24 entries for the exact
sequences both were verified against.

**What a real deployment would still need, if this decision is ever
revisited** (not attempted, not planned, listed here only so a future
session doesn't have to rediscover the gap from scratch): an actual
host, real DNS pointed at it, deleting `docker/Caddyfile`'s `tls
internal` line so Caddy's existing automatic-HTTPS machinery obtains a
real certificate instead, and a real `APP_KEY`/DB-credentials/secrets
set generated for that host specifically, never reusing this repository's
dev/placeholder values.

## Migration and rollback procedure

Standard Laravel migrations (`php artisan migrate`) apply to any
instance, including this local demo deployment, the same way they apply
to a self-hoster's install — nothing demo-specific here. What *is*
demo-specific and does not exist for a self-hoster: the demo's own
"rollback" primitive is `php artisan demo:reset` (built Session 22,
verified against a real running deployment Session 24), not a database
restore. A demo instance is never expected to hold state worth
restoring; a bad reset just runs again.

## Configuration and secrets

- `DEMO_MODE`/`DEMO_RESET_SCHEDULE` (`.env.example`) do something as of
  Session 22 (`config/demo.php`, `HandleInertiaRequests`,
  `ResetDemoInstanceCommand`); Session 24 adds `DEMO_VIEWER_EMAIL`/
  `DEMO_VIEWER_PASSWORD` (`B-08`) with real, documented defaults —
  `ResetDemoInstanceCommand` now re-creates exactly this one fixed
  account on every reset. See `09-decision-log.md`'s Session 22 and 24
  entries for the full account.
- Every other secret this app needs (`APP_KEY`, DB credentials,
  S3-compatible object storage keys, `AUDIT_CHAIN_ANCHOR_DESTINATION`)
  is already externalised to environment variables per
  `06-security-threat-model.md`'s "Secrets management." **By decision
  (Session 24), this project does not provision or scope real secrets
  for isolated infrastructure** — Demo Instance Data Safety control 5
  is explicitly not-applicable given no real infrastructure exists, not
  a gap awaiting a future session. The local placeholder-backed
  deployment reuses the same dev-only placeholder values
  `docker-compose.yml`/`docker-compose.prod.yml` already use, which is
  correct for what this proof actually is.
- **TLS: `docker/Caddyfile` now has a real TLS configuration**, using
  `tls internal` (Caddy's own offline local-CA issuance) against the
  placeholder domain `demo.privacy-forge.example`, substituting for real
  ACME/Let's Encrypt issuance — proven to genuinely terminate HTTPS and
  proxy to the app (`09-decision-log.md`'s Session 24 entry). Getting a
  *real* certificate would need exactly one change (delete the `tls
  internal` line and point the site address at a real, owned domain) —
  documented in the Caddyfile itself, not undecided, but not attempted,
  per this session's descoping decision.

## Observability: logs, metrics, traces, health checks

- `GET /up` (Laravel's built-in health check, exercised by
  `tests/Feature/EnvironmentHealthTest.php`, `docker-compose.yml`'s own
  `healthcheck:` blocks, and `docker-compose.prod.yml`'s `web` service
  healthcheck) is the only health check that exists today, and it's what
  Session 24 verified over real HTTPS. Wiring it into a real host's
  liveness-check mechanism (load balancer, platform-native probe, etc.)
  is not applicable, by decision — no such host exists or is planned.
- No metrics, tracing, or log aggregation exists or is planned — matches
  this project's own stated scale (a portfolio demo, not a production
  SLA commitment, per NFR-011). `LOG_CHANNEL=stack` writing to local
  files (or the host's captured stdout/stderr, depending on the platform)
  is sufficient for this project's actual ambition here.
- **One concrete, planned exception, now moot by decision:**
  `AnchorAuditChainCommand` (already built, ADR-0003/R-04) alerts
  (`Log::critical`, already implemented) on a failed anchor write — this
  would need a real destination a human would actually see on a real
  hosted instance. Since Session 24 descopes real hosting, there is no
  real destination to wire this to, and none is being added; if this
  project is ever actually deployed for real, this remains the one
  concrete follow-up item.

## Dashboards and alerts (each links a runbook)

None exist, and — per the Session 24 decision — none are planned. The
one alert this project's own threat model previously required before a
real go-live, Demo Instance Data Safety control 5's "alerting on
approach to [spend] cap," is explicitly not-applicable: there is no
spend and no cap to alert on, by decision, not by oversight.

## Runbooks

| Runbook | Status |
|---|---|
| Scheduled demo reset | **Verified working, Session 24**, against the real local production-shape deployment: `php artisan demo:reset` (`config('demo.enabled')`-gated) genuinely wipes real data entered after a prior reset and re-seeds the baseline plus the fixed demo-viewer account. Scheduled via `routes/console.php` + `DEMO_RESET_SCHEDULE`. Never run by an actual long-lived cron scheduler against a persistently-running instance — there is no such instance, by decision — only invoked directly each time it's been verified. |
| Manual/emergency demo reset | Same command, run on demand: `php artisan demo:reset` (refuses unless `DEMO_MODE=true`, see `ResetDemoInstanceCommand`'s class comment). |
| Chain verification | Already exists (`php artisan audit:verify-chain`, ADR-0003) — applies to this local deployment the same as any other. |
| Rotate the demo-viewer credential | `DEMO_VIEWER_EMAIL`/`DEMO_VIEWER_PASSWORD` (`.env.example`) — change either and re-run `demo:reset` (or recreate the container so the new values are actually picked up — see the OPcache note in `09-decision-log.md`'s Session 24 entry). Deliberately not a "leaked secret" runbook in the real-incident sense, since Demo Instance Data Safety control 5 (real isolation) is not-applicable here — there is no real public exposure for a leak to matter against. |
| Full demo instance teardown/redeploy | `docker compose -f docker-compose.prod.yml -p privacy-forge-prod down -v && docker compose -f docker-compose.prod.yml -p privacy-forge-prod up -d --build` — verified this session as part of proving the stack from a clean state. Nothing beyond this exists or is needed, since there is no real hosting target this maps onto. |

## Backup and restore (last verified: N/A — see below)

**Deliberately not needed** — Demo Instance Data Safety's whole design
(scheduled reset to synthetic seed data) means this deployment is never
expected to hold anything worth backing up; a lost instance is
redeployed from scratch (verified above), not restored from a backup.
Backup/restore remains a self-hoster's own concern for their own real
instance and data — this project provides no managed backup service for
anyone's instance, demo included, and (per the Session 24 decision) has
no real instance of its own to back up in the first place.

## Capacity and cost notes

- **No spend cap exists because, by explicit decision (Session 24), no
  real infrastructure exists or is planned.** This is marked **not
  applicable**, not an unmet launch blocker — T-20 (`06-security-threat-
  model.md`) is re-scored accordingly. If this project is ever actually
  deployed to real infrastructure in the future, a real, verified cap
  and alerting on approach to it becomes required again, exactly as
  originally specified.
- Expected load is trivial (a portfolio demo, `NFR-011` — best-effort,
  no SLA) — moot for the same reason: there is no real load against real
  infrastructure to size for.

---

## Sessions A/B/C: closed, by build (A) and by explicit descoping decision (B/C) — Session 24

`00-project-brief.md` (Session 1) originally decided **that** a public
hosted demo instance will exist, naming non-negotiable constraints
(synthetic data only, isolated infrastructure, spend cap, scoped
credentials) without ever naming a platform. Session 22 confirmed no
hosting target had been chosen; Session 23 confirmed no cloud account or
provisioning CLI exists in this environment at all. **Session 24 resolves
this not by choosing a platform, but by deciding not to provision one** —
see `09-decision-log.md`'s Session 24 entry for the full reasoning. The
three-session plan below (originally written at Session 22) is kept for
history, annotated with how each session's exit criterion was actually
closed.

**Recommendation, now moot by decision:** the original plan recommended a
single small VPS running `docker compose` directly over a managed
container platform, reasoning that this project's whole runtime is
already `docker-compose.yml`-shaped and isn't optimising for scale
(`NFR-011`). This reasoning is preserved here for a future session that
might revisit the Session 24 decision, but it was never acted on — no VPS
or any other host was provisioned, and none is planned.

### Go/no-go checklist — exercised against the local, placeholder-backed deployment (Session 24)

Originally written as "what must exist before a real public URL can go
live." Re-scoped by the Session 24 decision: items that only make sense
against real infrastructure are marked not-applicable, by decision, not
silently skipped; everything else was actually exercised, not merely
planned.

1. **A real production application image (`B-06`).** **Done, Session
   23; re-verified over real HTTPS, Session 24.** `docker/Dockerfile.prod`
   + `docker-compose.prod.yml` — PHP-FPM + Caddy. Verified locally over
   real HTTP (Session 23) and real HTTPS with a genuinely validated
   certificate chain against a placeholder domain (Session 24).
2. **The hosting target actually decided and provisioned.** **Not
   applicable, by decision (Session 24).** Real infrastructure is
   explicitly out of scope for this portfolio build — see
   `09-decision-log.md`.
3. **TLS.** **Done, against a placeholder domain (Session 24).**
   `docker/Caddyfile`'s `tls internal` — genuinely terminates HTTPS,
   proven with a real certificate-chain validation, not a real-domain
   ACME certificate (out of scope per item 2).
4. **A real spend cap, verified, not assumed.** **Not applicable, by
   decision (Session 24).** No infrastructure exists to spend against.
5. **`DEMO_MODE=true` set, and `demo:reset` actually scheduled and
   proven to run.** **Done against the local placeholder-backed
   deployment, Session 24** — `DEMO_MODE=true` set as a real container
   environment variable (not patched at runtime — see the OPcache
   finding in `09-decision-log.md`), `demo:reset` proven to genuinely
   reset real data, not merely exit successfully. Never run by a
   long-lived cron scheduler against a persistently-running instance,
   since none exists, by decision.
6. **A real decision on `B-08`** (scoped per-visitor demo identity).
   **Resolved, Session 24** — a fixed, documented demo-viewer
   credential, re-created by every `demo:reset`, verified with a real
   login and a real authenticated API call. See `09-decision-log.md` for
   why this simplification of the original per-visitor design is
   acceptable specifically given no live public exposure.
7. **A decision on `B-07`** (synthetic demo content). **Resolved by
   decision, Session 24** — the existing minimal baseline is decided as
   sufficient for this project's actual remaining purpose; richer
   content is downgraded to non-blocking backlog polish.

### Sessions A/B/C — outcome (originally "recommended session breakdown")

1. **Session A — Production image + infra provisioning.** **Image half
   done (Session 23). Infra half explicitly descoped, not merely still
   pending (Session 24)** — see `09-decision-log.md`. Exit criterion met
   for what this project actually does: the app responds to `GET /up`
   over the local production-shape stack, over both HTTP (Session 23)
   and HTTPS (Session 24).
2. **Session B — Demo-safety verification + DNS/TLS.** **Done against
   placeholder values instead of real DNS/a real domain, Session 24**
   — `DEMO_MODE=true` set for real, banner and `demo:reset` confirmed
   working against this real (if local) deployment; `B-08` resolved with
   a real, working (if simplified) design; TLS wired via `tls internal`
   against the placeholder domain. Exit criterion re-scoped: a
   placeholder domain, over genuinely-validated HTTPS, with `demo:reset`
   proven to actually reset real data — not a real domain, since none is
   being registered.
3. **Session C — Go-live checklist + `B-07` content decision.** **Done,
   re-scoped, Session 24** — the go/no-go checklist above was exercised
   item by item against the local deployment, with infrastructure-only
   items explicitly marked not-applicable; `B-07` resolved by decision.
   `01-scope-and-non-goals.md`'s MVP checklist's ninth item is checked
   off on this basis — see that document and `12-session-handoff.md`
   for what this does and does not mean for "v1 complete" as a whole.

Each session's exit criterion remains independently checkable — a future
session (or reviewer) can verify what Session A/B/C each actually
produced without needing to re-derive it from this narrative alone.
