# Deployment and Operations
> Purpose: how this runs, and how someone else keeps it running
> Project: privacy-forge (public)
> Last updated: 2026-08-18 (Session 22 — first real content; this file was
> an empty template through Session 21)

**Scope note.** This project has two genuinely different "deployments":
(1) a self-hoster's own instance, which is fully covered by the README
and `docker-compose.yml` and is *not* this project's operational
responsibility (per `14-maintenance-and-retirement.md` — "every
deployment is a self-hoster's own instance"); and (2) **the public demo
instance** (`00-project-brief.md`, Session 1's "Demo/hosting decision"),
which *is* this project's own operational responsibility, is the one
remaining unchecked MVP boundary item, and is what this document is
actually about. Nothing below applies to a self-hoster's install.

## Environments

| Environment | Purpose | Status |
|---|---|---|
| Local dev | `docker-compose.yml` (`app`/`worker`/`postgres`/`redis`/`minio`/`frontend`) | Exists, used every session |
| CI | `.github/workflows/ci.yml` — Pint, Larastan, Pest, ESLint, CodeQL, `osv-scanner`, OpenAPI validation | Exists |
| Public demo | The subject of this document | **Does not exist yet.** No hosting target decided, no infrastructure provisioned, no DNS, no TLS. |

There is no staging environment and none is planned — a single-org,
self-hosted product has no shared production environment of its own to
stage changes against; the demo instance is a reviewer-facing
convenience, not a staging tier for anything.

## Build and release pipeline

- CI (`.github/workflows/ci.yml`) already runs on every PR/push to
  `main`: `php-quality` (Pint, Larastan level 8, Pest), `frontend`
  (ESLint), `security` (CodeQL, `osv-scanner`), `dependency-governance`
  (ADR-0008's safeguard), and OpenAPI spec validation. This is the
  release gate for the *codebase*; it says nothing about deployment,
  since nothing is deployed anywhere today.
- **`docker/Dockerfile`'s `runtime` target is a development image**,
  despite the file's own header comment claiming otherwise (`php artisan
  serve`, Laravel's own docs call this "not intended for production" —
  single-threaded, no process manager, no static-asset serving strategy
  beyond Vite's dev server). **No production-grade image (PHP-FPM + a
  real web server) exists anywhere in this repository** — this was
  found this session to be a real documentation/reality drift (a
  comment claiming "built at Session 8" for something that was never
  built; see `09-decision-log.md`'s Session 22 forensic-finding entry
  and `11-backlog.md`'s `B-06`). **This is a hard blocker for the demo
  going live on a real public URL** and must be built before any
  hosting decision is executed, not assumed to already exist.
- No image registry is configured; no CD pipeline exists; nothing
  publishes a build artifact anywhere today.

## Deployment procedure

**Status: does not exist. This section is a plan, not a runbook** — see
"The actual deployment session(s)" below for what needs to happen before
this section can honestly describe a real procedure instead of a plan
for one.

## Migration and rollback procedure

Standard Laravel migrations (`php artisan migrate`) apply to any
instance, including a future demo one, the same way they apply to a
self-hoster's install — nothing demo-specific here. What *is*
demo-specific and does not exist for a self-hoster: the demo's own
"rollback" primitive is `php artisan demo:reset` (built this session,
Part B groundwork — see below), not a database restore. A demo instance
is never expected to hold state worth restoring; a bad reset just runs
again.

## Configuration and secrets

- `DEMO_MODE`/`DEMO_RESET_SCHEDULE` (`.env.example`) now actually do
  something as of this session (`config/demo.php`,
  `HandleInertiaRequests`, `ResetDemoInstanceCommand`) — previously
  documented with zero code reading either value since Session 4. See
  `09-decision-log.md`'s Session 22 entry for the full account.
- Every other secret this app needs (`APP_KEY`, DB credentials,
  S3-compatible object storage keys, `AUDIT_CHAIN_ANCHOR_DESTINATION`)
  is already externalised to environment variables per
  `06-security-threat-model.md`'s "Secrets management" — the demo
  instance needs its own real values for all of these, scoped to
  isolated infrastructure (Demo Instance Data Safety control 5), not
  shared with any other environment. None of these values exist yet;
  none should be generated or committed anywhere until the actual infra
  session provisions them.
- **No TLS configuration exists anywhere in this repository.** A real
  public URL needs a certificate (e.g. Let's Encrypt via the chosen
  host's own mechanism, or a platform that terminates TLS for you) —
  entirely undecided, blocked on the hosting target.

## Observability: logs, metrics, traces, health checks

- `GET /up` (Laravel's built-in health check, exercised by
  `tests/Feature/EnvironmentHealthTest.php` and `docker-compose.yml`'s
  own `healthcheck:` blocks) is the only health check that exists today.
  It would need to be wired into whatever the chosen host uses for
  liveness checks (load balancer health check, platform-native probe,
  etc.) — mechanism depends entirely on the undecided hosting target.
- No metrics, tracing, or log aggregation exists or is planned — matches
  this project's own stated scale (a portfolio demo, not a production
  SLA commitment, per NFR-011). `LOG_CHANNEL=stack` writing to local
  files (or the host's captured stdout/stderr, depending on the platform)
  is sufficient for this project's actual ambition here.
- **One concrete, planned exception:** `AnchorAuditChainCommand`
  (already built, ADR-0003/R-04) should alert (`Log::critical`, already
  implemented) on a failed anchor write — on the demo instance this
  needs a real destination a human would actually see (even something as
  minimal as the platform's own log alerting, if it has one). Undecided
  until the hosting target is chosen.

## Dashboards and alerts (each links a runbook)

None exist. **The only alert this project's own threat model requires
before go-live** is Demo Instance Data Safety control 5's "alerting on
approach to [spend] cap" — entirely dependent on whatever billing/cost
controls the chosen hosting platform offers (see "Cost notes" below).

## Runbooks

| Runbook | Status |
|---|---|
| Scheduled demo reset | **Command exists** (`php artisan demo:reset`, this session). Scheduled via `routes/console.php` + `DEMO_RESET_SCHEDULE`. Not yet run against a real deployed instance — only against dev/test databases so far (see `12-session-handoff.md`'s validation section). |
| Manual/emergency demo reset | Same command, run on demand: `php artisan demo:reset` (refuses unless `DEMO_MODE=true`, see `ResetDemoInstanceCommand`'s class comment). |
| Chain verification | Already exists (`php artisan audit:verify-chain`, ADR-0003) — applies to the demo instance the same as any other. |
| Rotate a leaked demo secret | **Does not exist as a written procedure.** Depends on the hosting target's own secret-management mechanism. |
| Full demo instance teardown/redeploy | **Does not exist.** Depends on the hosting target and whether infrastructure is defined as code (recommended below) or provisioned by hand. |

## Backup and restore (last verified: NEVER — update this)

**Deliberately not needed for the demo instance** — Demo Instance Data
Safety's whole design (scheduled reset to synthetic seed data) means the
demo is never expected to hold anything worth backing up; a lost demo
instance is redeployed from scratch, not restored from a backup. This
line item stays honestly "never verified" because backup/restore is a
self-hoster's own concern (their own data, their own responsibility),
and this project provides no managed backup service for anyone's
instance, demo included.

## Capacity and cost notes

- **No spend cap exists because no infrastructure exists.** Demo
  Instance Data Safety control 5 names this as a launch blocker (T-20),
  not optional — the actual deployment session must not go live without
  a real, verified cap and alerting on approach to it, on whichever
  platform is chosen.
- Expected load is trivial (a portfolio demo, `NFR-011` — best-effort,
  no SLA) — this favours a hosting choice with a genuinely enforceable
  free/low-cost tier over one that's cheap only until traffic spikes.

---

## The actual deployment session(s) — a concrete plan

### Was a hosting target ever decided? No — checked directly, not assumed.

`00-project-brief.md` (Session 1) decided **that** a public hosted demo
instance will exist, and named the non-negotiable constraints (synthetic
data only, isolated infrastructure, spend cap, scoped credentials) — it
never named a platform. `03-architecture.md`, `01-scope-and-non-goals.md`,
and `14-maintenance-and-retirement.md` all reference "the public demo
instance" the same way, as a decided *feature*, never a decided
*platform*. This was checked directly against all four files this
session, not inferred from silence. **A hosting target is a genuine open
decision for the next session, not a rediscovery of something already
chosen.**

**Recommendation (not a commitment — the next session should confirm,
not blindly follow):** a single small VPS (e.g. Hetzner, DigitalOcean, or
similar) running `docker compose` directly, rather than a managed
container platform (Fly.io, Railway, Render, ECS, etc.). Reasoning:
- This project's whole runtime is already `docker-compose.yml` — a VPS
  running Compose directly reuses that file almost as-is (once `app`
  points at a real production image, `B-06`), rather than translating it
  into a platform-specific manifest as a second thing to keep in sync.
- A single small VPS has the most predictable, easiest-to-cap cost of
  any option here — a fixed monthly price, not a metered bill that needs
  its own alert-before-surprise mechanism on top of a hard cap.
- This project explicitly is not optimising for scale (`NFR-011`) — the
  operational simplicity of "one box, one `docker compose up -d`"
  outweighs a managed platform's elasticity, which this project has no
  use for.
- Counter-consideration, stated plainly: a managed platform would remove
  the OS-level patching/security-update burden a raw VPS carries. If the
  next session weighs that burden as higher than this recommendation
  assumes, that's a legitimate reason to pick differently — this is a
  recommendation with reasoning attached, not a foreclosed decision.

### What must exist before a real public URL can go live (the actual go/no-go checklist)

1. **A real production application image (`B-06`).** PHP-FPM + a real
   web server (nginx or Caddy — Caddy's automatic TLS is a genuine point
   in its favour if the chosen host doesn't already terminate TLS for
   you), replacing `php artisan serve`. Does not exist; must be built.
2. **The hosting target actually decided and provisioned.** Real
   infrastructure, isolated from anything else (Demo Instance Data
   Safety control 5) — not attempted this session, per Part B's explicit
   "planning only" scope.
3. **TLS.** Genuinely undecided mechanism until (2) is picked.
4. **A real spend cap, verified, not assumed.** Whatever the chosen
   platform's actual mechanism is (billing alert, hard resource limit,
   etc.) — must be *tested* (e.g. confirm the alert actually fires
   before relying on it), not just configured and trusted.
5. **`DEMO_MODE=true` set, and `demo:reset` actually scheduled and
   proven to run** on the real deployed instance — the code exists
   (this session) and is tested against dev/test databases only; it has
   never run against a real deployed instance, because none exists.
6. **A real decision on `B-08`** (scoped per-visitor demo identity) —
   until this is designed, the demo instance has no answer to "how does
   a reviewer actually log in," beyond either (a) a shared credential
   (exactly what control 2 exists to avoid) or (b) no staff-side demo at
   all, only the public consent/DSAR-submission surface. **This is a
   real open question the next session must resolve, not a detail.**
7. **A decision on `B-07`** (synthetic demo content) — or an explicit,
   stated decision to launch with the minimal baseline
   (`demo:reset`'s current output) and treat richer content as a
   post-launch iteration. Either is legitimate; silence is not.

### Recommended session breakdown (this is itself the plan's main deliverable)

**Do not attempt all of this in one session** — each of the three below
has a different failure mode if rushed, and each produces a real,
checkable artifact the next can build on.

1. **Session A — Production image + infra provisioning.** Build the
   real PHP-FPM/web-server image (`B-06`); decide and provision the
   hosting target (confirming or overriding the recommendation above);
   get a real `docker compose up -d` running on real infrastructure,
   reachable only by IP (no DNS/TLS yet). Exit criterion: the app
   responds to `GET /up` over the real infrastructure's public IP.
2. **Session B — Demo-safety verification + DNS/TLS.** Set
   `DEMO_MODE=true` for real and confirm the banner/reset actually work
   against the real deployed instance (not just dev/test, as this
   session's validation was limited to); resolve `B-08` (visitor
   identity) with a real design, not a placeholder; wire DNS + TLS.
   Exit criterion: a real domain, over HTTPS, with `demo:reset` proven
   to have actually run on schedule at least once (not just unit-tested).
3. **Session C — Go-live checklist + `B-07` content decision.** Run
   Success Metric #5's manual pre-launch check for real (no real PII,
   spend cap configured *and verified*, credentials scoped, network
   isolation confirmed) against the real instance; decide and either
   build or explicitly defer `B-07`; update this document's "Deployment
   procedure" section from "does not exist" to a real, followed
   procedure. Exit criterion: `01-scope-and-non-goals.md`'s MVP
   checklist's ninth item can honestly be checked off.

Each session's exit criterion is independently checkable — a future
session (or reviewer) can verify Session A happened without needing
Session B or C to also be done, which is the point of splitting this
rather than treating "deploy the demo" as one undifferentiated task.
