# Dependabot status

_Last updated: 2026-10-09. Maintained during the Dependabot clean-up pass; update when the state changes._

## Configuration

- Ecosystems covered: composer (`/`), npm (`/`), docker (`/docker`), github-actions (`/`), docker-compose (`/`).
- Grouping: `minor-and-patch` for every ecosystem (open-PR limit 5 each).
- Schedule: weekly.
- Ignore rules: composer: `inertiajs/inertia-laravel` majors (paired with `@inertiajs/vue3`, which also ignores majors); npm: `eslint` majors (eslint-plugin-vue 9 peers eslint <= 9), `eslint-plugin-vue` majors (10 drops the legacy eslintrc presets), `tailwindcss` majors (4 moves the PostCSS plugin), `@inertiajs/vue3` majors; docker-compose image majors (stateful services need a deliberate migration).

## State at last update

- Open Dependabot PRs: 0 (each merged or closed only after reading its checks).
- Default-branch CI: green at last check.
- Last full rescan: 2026-10-09. Checked open PRs (none), default-branch and scheduled CI, Dependabot update jobs, ecosystem coverage (no new manifests since 2026-10-08), Actions pins, exemption expiry dates and stray branches, plus three new dimensions: branch-protection required contexts against the check runs a PR actually produces, the repo's `security_and_analysis` settings, and check-run annotations on `main`. No required context is stale. The annotations showed `ubuntu-latest` moving to Ubuntu 26 from 2026-10-19, so every job is now pinned to `ubuntu-24.04` (see Notes). The full-history gitleaks scan was not repeated: the only commits since 2026-10-08 are docs and CI changes, each scanned by the push-run gitleaks job.

## Time-limited exemptions

- `osv-scanner.toml`: `GHSA-vfj7-8cjw-p6xm` (braces, no patched version, dev-only via the frontend toolchain), `ignoreUntil` 2026-11-15.

## Notes

- `package.json` carries an `overrides` entry pinning `postcss-selector-parser` to `^7.1.6` to clear GHSA-rj75-hqrm-r3gf; drop it once upstream ranges allow the fixed version.
- `.gitleaksignore` (added 2026-10-08): one fingerprint, a prose line that lists which secrets are kept in environment variables, in `docs/project-memory/08-deployment-and-operations.md` (commit c23f6657). CI's gitleaks job only scans new commits, so it never failed; a full-history scan did.
- When documenting a gitleaks false positive, describe it rather than quoting it. The first version of this note quoted the flagged prose, and the PR's own gitleaks check failed on the quote.
- Every workflow declares a top-level `permissions: contents: read` (added 2026-10-08, rescan cycle 3). Jobs that need more, such as CodeQL's `security-events: write`, declare it at job level.
- Merge policy (deliberate choice by the repo owner, 2026-10-08): every PR, major-version dependency bumps included, is merged as soon as all of its required checks are green, confirmed per PR. This repo is a code showcase with no business or sensitive dependency, so green checks are the only gate. Red, pending or conflicted PRs are fixed or closed instead.
- Every Linux job runs on `ubuntu-24.04` (pinned 2026-10-09; it is what `ubuntu-latest` resolved to). GitHub moves `ubuntu-latest` to Ubuntu 26 from 2026-10-19, and an unattended image change could turn every check red at once. Move to `ubuntu-26.04` deliberately, in one PR whose CI has run on it. Dependabot does not bump `runs-on` labels.
- Pending owner action (found 2026-10-09): Dependabot security updates are disabled here (enabled on the other 11 repos), and the `dependency-governance` job (ADR-0008) runs on every PR but is not a required check, so under the merge policy a red result would not block a merge. Needs the Administration permission on the owner's token; escalation requested 2026-10-09.

## Deferred (not re-raised each pass)

- Ignored major versions are listed in `.github/dependabot.yml` with the reason for each.
- Re-check exemptions before their `effectiveUntil` date (2026-11-15) and drop them once upstream fixes ship.
- Alerts read 2026-10-09 with the repo owner's PAT, run on their machine (Claude sessions still get 403: the proxy sends a GitHub App token instead of `GH_ALERTS_TOKEN`, even a PAT passed explicitly). No open Dependabot or code-scanning alerts.
