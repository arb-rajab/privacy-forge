# Session Handoff

## Project
- Repository: `privacy-forge` (https://github.com/arb-rajab/privacy-forge)
- Public or private: public (flagship)
- Product/domain: Data-privacy / consent & DSAR compliance engine
- Current version or branch: `main` (unreleased, pre-v0.1.0)

## Session completed
- Session number and title: **Session 23 — Deployment Session A: build
  B-06's real production image, verify it locally over real HTTP.**
- Objective: execute Session A of the three-session deployment plan
  `08-deployment-and-operations.md` (Session 22) laid out — build the
  real PHP-FPM/web-server production image (closing `B-06`) and get it
  running, reachable, and proven — plus, before any of that, re-verify
  from raw git history whether B-06 was ever an actual narrated-vs-real
  contradiction (ADR-0008-shaped) rather than an unfulfilled plan.
- Status: **B-06 re-verified as the milder shape (see below), then built
  for real and verified locally over real HTTP (build → migrate → seed →
  real login → real authenticated API call). Hosting/infrastructure
  provisioning — the other half of Session A — was not attempted: this
  environment has no cloud account or provisioning CLI at all.** All 186
  Feature tests pass, Pint/Larastan (level 8)/ESLint clean, OpenAPI
  validates — unchanged, since no application code was touched this
  session, only Docker/deployment artifacts and documentation.

## B-06, re-verified from raw history before any other work

Checked directly, per this session's own first instruction, before
touching anything else:
- The "built at Session 8" comment was introduced by commit `d2611fa`
  (Session 5) — **three sessions before Session 8 happened.** At the
  time it was written it was a forward-looking plan, not a claim about
  present state.
- Session 8's own commit (`ae42449`) and handoff document were read in
  full: it shipped connector dispatch/callback/export-bundle/deletion-
  certificate work (US-007/008/009). **Zero mention of a production
  image, PHP-FPM, or a web server anywhere in it.**
- Session 13's decision-log entry, which Session 22 had cited as a
  second source, does not independently confirm the claim — it just
  repeats the Session 5 comment's assumption without checking `docker/`
  itself.
- **Conclusion: not the same shape as ADR-0008.** ADR-0008 was a real,
  executed divergence (`composer.json` actually bumped, in the same
  commit whose own prose said it wasn't). B-06 has no such artifact —
  no session's own handoff ever asserted the production image existed.
  It's a stale Session-5 forward plan, naming a target session that
  came and went on unrelated work, repeated uncritically once (Session
  13), and finally caught when Session 22 went looking for the artifact
  itself. Milder than ADR-0008, but the same lesson: a claim about
  repository state isn't verified state. See `09-decision-log.md`'s
  Session 23 entry for the full account. No ADR opened or reopened —
  Session 22's own filing of this as backlog/build work was already the
  right call.

## What was built: B-06, for real

- **`docker/Dockerfile.prod`** — two targets:
  - `app` — `php:8.3-fpm`, OPcache enabled with production settings,
    application code + compiled frontend assets (a `frontend-assets`
    stage runs `npm run build` at image-build time). No Node/npm/
    Playwright; no `sockets`/`pcntl` (test-only, per decision log
    Session 13 — this image never runs `tests/Browser/`).
  - `web` — Caddy, serves `public/build`'s compiled static output
    directly and reverse-proxies dynamic requests to `app` over
    FastCGI (`php_fastcgi`).
- **`docker/Caddyfile`**, **`docker/entrypoint.prod.sh`**,
  **`docker-compose.prod.yml`**, **`.dockerignore`** (new).
- **Two real bugs found and fixed while getting it running:**
  1. `frontend-assets`'s original `node:20-alpine` base hit npm's own
     documented optional-dependency bug (npm/cli#4828) —
     `package-lock.json` resolved the glibc rollup binary (matching
     `docker/Dockerfile.frontend` and CI, both glibc), and Alpine's musl
     has no matching binary. Fixed by switching to `node:20-slim`.
  2. Caddy's `php_fastcgi app:9000` 404'd on every request at first:
     Caddy's own `root` governs its own filesystem (for `file_server`),
     but the FastCGI `SCRIPT_FILENAME` it sends to the remote `app`
     container must match *that* container's filesystem
     (`/var/www/html/public`), not Caddy's (`/srv/public`). Fixed with
     an explicit `php_fastcgi { root /var/www/html/public }` override.
- **`route:cache` deliberately not attempted** in the entrypoint:
  `routes/web.php`'s Inertia page routes are closure-based, and
  Laravel's route cache cannot serialize closures. Checked before
  writing the entrypoint script, not discovered by a crash loop.
  `config:cache`/`view:cache` run at container start, not build time.
- **`docker/Dockerfile`'s and `docker-compose.yml`'s stale "built at
  Session 8" header comments corrected**, in the same session that
  builds the real thing, per Session 22's own instruction.

## Verified for real, not assumed

1. `docker compose -f docker-compose.prod.yml -p privacy-forge-prod up
   -d --build` — both images (`app`, `web`) build clean.
2. `GET /up` → `200` — Laravel's real health page, through Caddy → PHP-
   FPM. No dev server anywhere in the path.
3. `GET /` → `200` (the real Inertia homepage); a compiled static asset
   (`/build/assets/app-*.css`) → `200` via Caddy's `file_server`.
4. `docker run --entrypoint sh privacy-forge-app-prod:latest -c "which
   node npm"` → nothing found; `php -m` → no `sockets`/`pcntl`/`xdebug`
   — the image is actually lean, confirmed directly.
5. Real migrations against this stack's own fresh Postgres container,
   real `PolicyDefinitionSeeder` seeding, a real Owner created
   (`privacy-forge:create-owner`), a real `POST /login` (genuine
   CSRF/session cookie flow, not `actingAs()`), then a real
   authenticated `GET /api/v1/admin/audit-log` → `200`, returning that
   exact login's own `audit.log.view` audit entry. Proves the full
   stack — Caddy, PHP-FPM, Postgres, Redis-backed sessions, the ABAC
   gate — works end to end, the same standard Session 22/R-08
   established.

## What was explicitly NOT done this session, and why

1. **Real infrastructure was not provisioned. No cloud account was
   touched. No money was spent.** Checked directly: no
   `doctl`/`hcloud`/`flyctl`/`aws`/`az`/`gcloud`/`terraform` CLI exists
   in this environment, and none was installed or authenticated. This
   is flagged explicitly, not silently skipped or faked with a
   substitute — provisioning real infrastructure needs either a human
   with real cloud credentials acting directly, or a future session run
   somewhere those credentials/tools are available.
2. **The hosting-target decision was not reconfirmed or overridden** —
   Session 22's VPS + `docker compose` recommendation stands, unexamined
   further this session, since there was no real infrastructure to
   weigh it against yet.
3. **DNS, TLS, spend cap, and Demo Instance Data Safety control 5** —
   all infrastructure-dependent, all still not started, matching
   Session B's scope, not this one.
4. **`B-07` (demo content) and `B-08` (visitor identity) — not
   designed.** Neither blocks Session A's own exit criterion (both are
   named as Session B/C's own resolution points in the plan); correctly
   deferred, not silently dropped.
5. **No ADR opened or reopened.** GDPR-only, single-tenant, and the
   public-demo decision itself untouched.
6. **B-01, B-02, B-03 — unchanged, still open.** Out of scope.

## MVP boundary checklist — honest current count

**Unchanged at 8 of 9.** The ninth item ("a public demo instance running
on synthetic seed data, in isolated infrastructure, with a spend cap")
still describes an actually-deployed instance, not a built-and-locally-
verified image. **What changed:** Session A's own named blocker (`B-06`)
is now closed — the production image exists and is proven to work. What
remains for that checklist item is entirely infrastructure: provisioning,
DNS/TLS, a verified spend cap, and `B-07`/`B-08`. The next session (or a
human with real cloud credentials) has a concrete image to deploy, not a
Dockerfile still to write.

## Files created or changed

**New:**
- `docker/Dockerfile.prod`
- `docker/Caddyfile`
- `docker/entrypoint.prod.sh`
- `docker-compose.prod.yml`
- `.dockerignore`

**Changed:**
- `docker/Dockerfile` — corrected header comment (no longer claims a
  production image was "built at Session 8"; points at
  `docker/Dockerfile.prod` instead).
- `docker-compose.yml` — same correction in its header comment.
- `docs/project-memory/03-architecture.md` — corrected a related stale
  "Session 8" reference in the Scalability section to point at the real
  artifact and session.
- `docs/project-memory/08-deployment-and-operations.md` — updated to
  reflect B-06 closed, the go/no-go checklist's item 1 done, Session A
  marked partially complete with an honest exit-criterion status.
- `docs/project-memory/09-decision-log.md` — two new entries (the B-06
  raw-history re-verification; the Deployment Session A build/verify
  account, including both bugs found and fixed).
- `docs/project-memory/11-backlog.md` — `B-06` marked closed (image
  half), with the infra half named as the remaining gap.
- `docs/project-memory/12-session-handoff.md` (this file).

**Not changed:** any ADR, `01-scope-and-non-goals.md`'s GDPR-only/
single-tenant/public-demo decisions, any application PHP/JS source,
`composer.json`/`composer.lock`/`package.json` (no new dependencies —
`docker/Dockerfile.prod` uses only what's already declared), `B-01`
through `B-05`, `R-01` through `R-08`.

## Validation performed

- **`composer test` (Pest, dev stack) → 186/186 passed, 750 assertions
  (~78s)** — unchanged from Session 22, re-run to confirm this session's
  Docker/doc-only changes affected nothing application-side.
- **`composer lint` (Pint) → clean, 161 files.**
- **`composer analyse` (Larastan, level 8) → 0 errors, 68 files.**
- **`npm run lint` (ESLint) → clean.**
- **`docs/architecture/openapi.yaml` → valid**, same throwaway
  `python:3.12-slim`-container method prior sessions used.
- **Production stack, real HTTP walkthrough** — see "Verified for real,
  not assumed" above; the rigorous evidence this session adds beyond the
  existing Pest suite (which doesn't build or run Docker images at all).
- **Dev stack left in a working state** — `docker compose up -d`
  (unmodified `docker-compose.yml`) still running with its own DB
  migrated/seeded, exactly as before this session; the production stack
  (`docker-compose.prod.yml`, project name `privacy-forge-prod`) runs
  alongside it on distinct ports (8080/5433/6380/9002-3), not conflicting.

## Open questions and risks

- **R-01 through R-08 — not touched, none affected.** No unrelated
  application code changed this session.
  - R-07's rate-limit follow-up (due 2026-08-24) is still not due today
    (2026-08-18).
  - R-08 — unchanged, accepted residual risk.
- **B-01, B-02, B-03 — unchanged, still open.**
- **B-04, B-05 — closed (Session 22), unaffected this session.**
- **B-06 — closed (image half) this session.** Infra provisioning
  remains open, tracked in `08-deployment-and-operations.md`'s Session
  A/B breakdown, not re-filed as a new item.
- **B-07, B-08 — unchanged, still open**, correctly deferred to Session
  C and B respectively.
- **MVP boundary — still 8 of 9**, for the reason stated above.

## Next recommended session

**Immediate need: a human (or a session run with real cloud
credentials) must decide and provision the hosting target** — this
environment has none available, so this cannot be automated further
here. Once infrastructure exists (even bare, reachable only by IP):

1. Push/copy `docker-compose.prod.yml` + `docker/` to that host, supply
   a real `.env` (real `APP_KEY`, real DB/Redis/object-storage
   credentials — never this repo's dev placeholders), and run the exact
   sequence verified this session (`up -d --build`, `migrate --force`).
   This closes Session A's exit criterion for real (`GET /up` over a
   real public IP).
2. Then proceed to **Session B** (`08-deployment-and-operations.md`):
   `DEMO_MODE=true` for real, confirm the banner/`demo:reset` against
   the real deployed instance, resolve `B-08` (visitor identity) with a
   real design, wire DNS + TLS (Caddy auto-provisions TLS once given a
   real domain in `docker/Caddyfile` in place of `:80`).

Do NOT attempt Session C's go-live checklist or `B-07`'s content
decision before Session B is genuinely done, for the same reason Session
22 didn't rush Parts A and B together.

- Inputs required: `docs/project-memory/08-deployment-and-operations.md`
  (updated this session — read the go/no-go checklist and Session
  B/C description), `docs/project-memory/09-decision-log.md`'s Session
  23 entries, `docker-compose.prod.yml` + `docker/Dockerfile.prod` (the
  real image to deploy, not rebuild), `docker/Caddyfile` (needs a real
  domain name substituted for `:80` once DNS exists).

## Paste-into-new-session context

**Project:** privacy-forge — self-hostable, single-organisation consent,
DSAR, and data-retention engine for small SaaS teams, GDPR/UK-GDPR only
**Track:** public flagship
**Repository state:** branch `main`, unreleased (pre-v0.1.0), Session 23
complete.

**Current stack:** unchanged — no dependency versions touched this
session. PHP 8.3, Vue 3/Inertia, PostgreSQL 16, Redis 7, S3-compatible
storage. No new dependencies (the production image uses only packages
already in `composer.json`/`package.json`).

**Architecture decisions that must not be reversed:** all decisions from
Sessions 0-22 remain in force. Nothing about the stack, any ADR, or the
GDPR-only/single-tenant/public-demo decisions was touched this session.

**Implementation state:**
- Done: everything from Session 22, plus: `docker/Dockerfile.prod` +
  `docker-compose.prod.yml` (real production-shape image, PHP-FPM +
  Caddy), verified locally over real HTTP including a real login and a
  real authenticated, DB-backed API call.
- In progress: nothing mid-flight.
- **Known gaps to check first:** (1) real hosting infrastructure —
  still does not exist anywhere, no cloud credentials in this
  environment; (2) `B-08` — demo visitor identity undesigned; (3)
  `B-07` — demo content undesigned; (4) R-01 — still open, DB-level
  grant revocation for the audit log unbuilt; (5) R-07's rate-limit
  follow-up — re-check due 2026-08-24, not before; (6) R-08 — accepted
  residual, unchanged; (7) B-01/B-02/B-03 — unchanged, still open.
- Not started: real infra provisioning, DNS, TLS, a verified spend cap,
  `B-07`, `B-08` — all named in `08-deployment-and-operations.md`'s
  Session B/C scope.

**Constraints and non-goals:** unchanged since Session 1. Still at the
2-new-technology cap (ABAC, ASVS L2) — this session introduced no new
architectural pattern or dependency (Caddy is infrastructure/ops
tooling, not an application dependency or architectural pattern in the
ADR sense).

**Task for next session (single objective):** provision real hosting
infrastructure (a human decision/action outside this sandboxed
environment, or a session with real cloud credentials) and deploy the
already-built, already-verified production image to it — completing
Session A for real — then proceed to Session B (demo-safety
verification against the real instance, DNS/TLS). Do not attempt Session
C's scope in the same session as Session B.

**Files to attach or paste:**
- `docs/project-memory/12-session-handoff.md` (this file)
- `docs/project-memory/08-deployment-and-operations.md` (updated plan —
  read the go/no-go checklist and Session B/C descriptions)
- `docs/project-memory/09-decision-log.md`'s Session 23 entries
- `docker-compose.prod.yml`, `docker/Dockerfile.prod`, `docker/Caddyfile`
  (the real artifacts to deploy)

**Ground rules:** Do not change the stack. Do not reopen any ADR
(ADR-0001 through ADR-0008). Do not reopen GDPR-only/single-tenant/
public-demo. Do not spend real money or provision real infrastructure
without confirming the hosting choice and getting explicit sign-off
first — Session 22's VPS recommendation is a starting point, not a
mandate, and no infrastructure exists yet to have made that
irreversible. R-01 remains open; R-07's follow-up isn't due until
2026-08-24; R-08 is accepted residual — don't reopen any of them without
a genuine new finding.
