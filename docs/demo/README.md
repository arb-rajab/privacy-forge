# Demo screenshots

Real screenshots of the actual running application, captured against a
freshly seeded local dev stack (`docker compose up --build`, following
this repository's own README quickstart) — not mockups, and not the
production/demo-hosting stack described in
`../project-memory/08-deployment-and-operations.md`.

| File | Shows |
|---|---|
| [`screenshots/01-login.png`](screenshots/01-login.png) | Staff login (`/login`), R-05 |
| [`screenshots/02-dsar-queue.png`](screenshots/02-dsar-queue.png) | DSAR queue (`/admin/dsar`) with a real `pending_verification` export request and a real `partially_complete` erasure request — the second one reached that status because the reference connector's dispatch genuinely failed against this local stack's own state, and `App\Services\DsarCompletionEvaluator` correctly reported the honest partial outcome (FR-009) rather than a false `complete`. That is the system working as designed, not a staged success state. |
| [`screenshots/03-retention-dry-run.png`](screenshots/03-retention-dry-run.png) | Retention dry-run preview (`/admin/retention`), ADR-0002 — a real "Preview (dry run)" click against one withdrawn, retention-eligible consent record, showing the real `affected_record_count`/sample ID response with no data changed |
| [`screenshots/04-ropa-export.png`](screenshots/04-ropa-export.png) | RoPA export (`/admin/ropa`), US-013/FR-016 |
| [`screenshots/05-audit-log.png`](screenshots/05-audit-log.png) | Audit log (`/admin/audit-log`) with real entries from the actions above, including a genuine `connector.callback.anomaly` deny entry produced by the same connector failure noted above |

## Why screenshots, and how they were captured

[R-08](../project-memory/10-risk-register.md) is a formally accepted
residual risk: the Pest Browser Testing suite's Docker-launched Chromium
(via `pestphp/pest-plugin-browser` → Playwright) hangs reliably on this
host class, confirmed across three sessions of investigation on two
independent hosts. Re-running that same path to produce a demo recording
would very likely reproduce the same hang, so it wasn't attempted again
here.

Instead, these screenshots were captured by a small script driving a
**native, host-installed Chrome** directly over the Chrome DevTools
Protocol (raw WebSocket, no Playwright/Puppeteer dependency) — a
different mechanism from the one R-08 investigated: no Docker-launched
browser, no Pest orchestration, no `child_process.spawn` fd-inheritance
path. The app's dev-server Vite client is hardcoded to `0.0.0.0:5173`,
which isn't a connectable destination address from a host client; the
script works around this with a `--host-resolver-rules=MAP 0.0.0.0
127.0.0.1` Chrome launch flag, a browser-level rewrite that touches no
application file. Real staff accounts, a real consent purpose, a real
DSAR submission, a real identity-verification/erasure-approval pair (two
different admins, per ADR-0007's separation-of-duties), a real retention
policy, and a real dry-run preview were created through the application's
own real HTTP endpoints — the same ones the README's own "Try it" section
walks a human through — before each page was captured.

This is a one-off capture, not a repeatable automated step in CI: it
exists to produce a real demo asset given R-08's constraint, not to
replace the accepted-residual-risk browser-automation gap that R-08
describes. That gap — a real browser click proving the admin dashboard's
client-side Vue rendering end to end, driven by an automated,
CI-repeatable suite — remains open, per R-08's own standing assessment.

## If you want to re-capture these, or record a video instead

1. Bring up the dev stack per the README quickstart and complete step 0
   (seed, register the reference connector, create two owner accounts).
2. Submit at least one DSAR, verify identity as one admin, approve
   erasure as the *other* admin (ADR-0007), and create one active
   retention policy with at least one retention-eligible record so the
   dry-run preview has something real to show.
3. For a video instead of stills: any standard screen recorder
   (OS-native or OBS) pointed at a real browser window driving the
   README's "Try it" walkthrough end to end works — this does not depend
   on Playwright/Pest at all, so R-08 does not block it. This wasn't
   done in this session in favour of the stills above, not because it
   isn't possible.
