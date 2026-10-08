# Dependabot status

_Last updated: 2026-10-08. Maintained during the Dependabot clean-up pass; update when the state changes._

## Configuration

- Ecosystems covered: composer (`/`), npm (`/`), docker (`/docker`), github-actions (`/`), docker-compose (`/`).
- Grouping: `minor-and-patch` for every ecosystem (open-PR limit 5 each).
- Schedule: weekly.
- Ignore rules: composer: `inertiajs/inertia-laravel` majors (paired with `@inertiajs/vue3`, which also ignores majors); npm: `eslint` majors (eslint-plugin-vue 9 peers eslint <= 9), `eslint-plugin-vue` majors (10 drops the legacy eslintrc presets), `tailwindcss` majors (4 moves the PostCSS plugin), `@inertiajs/vue3` majors; docker-compose image majors (stateful services need a deliberate migration).

## State at last update

- Open Dependabot PRs: 0 (each merged or closed only after reading its checks).
- Default-branch CI: green at last check.
- Last full rescan: 2026-10-08. Checked open PRs, default-branch and scheduled CI, Dependabot update jobs, ecosystem coverage against the manifests in the repo, Actions pins, exemption expiry dates, stray branches, and (new this pass) a local full-history gitleaks 8.28.0 scan. One finding, a false positive now listed in `.gitleaksignore` (see Notes).

## Time-limited exemptions

- `osv-scanner.toml`: `GHSA-vfj7-8cjw-p6xm` (braces, no patched version, dev-only via the frontend toolchain), `ignoreUntil` 2026-11-15.

## Notes

- `package.json` carries an `overrides` entry pinning `postcss-selector-parser` to `^7.1.6` to clear GHSA-rj75-hqrm-r3gf; drop it once upstream ranges allow the fixed version.
- `.gitleaksignore` (added 2026-10-08): one fingerprint, a prose line that lists which secrets are kept in environment variables, in `docs/project-memory/08-deployment-and-operations.md` (commit c23f6657). CI's gitleaks job only scans new commits, so it never failed; a full-history scan did.
- When documenting a gitleaks false positive, describe it rather than quoting it. The first version of this note quoted the flagged prose, and the PR's own gitleaks check failed on the quote.
- Every workflow declares a top-level `permissions: contents: read` (added 2026-10-08, rescan cycle 3). Jobs that need more, such as CodeQL's `security-events: write`, declare it at job level.
- Merge policy (deliberate choice by the repo owner, 2026-10-08): every PR, major-version dependency bumps included, is merged as soon as all of its required checks are green, confirmed per PR. This repo is a code showcase with no business or sensitive dependency, so green checks are the only gate. Red, pending or conflicted PRs are fixed or closed instead.

## Deferred (not re-raised each pass)

- Ignored major versions are listed in `.github/dependabot.yml` with the reason for each.
- Re-check exemptions before their `effectiveUntil` date (2026-11-15) and drop them once upstream fixes ship.
- Dependabot/code-scanning alert API (2026-10-08): not readable. The proxy-injected `GH_ALERTS_TOKEN` is sent, but `GET /repos/arb-rajab/*/dependabot/alerts` and `/code-scanning/alerts` return 403 "Resource not accessible by integration" on all 12 repos; the token lacks the `vulnerability_alerts` / `security_events` read permissions. Alert state remains unverified.
