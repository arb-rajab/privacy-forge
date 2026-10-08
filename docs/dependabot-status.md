# Dependabot status

_Last updated: 2026-10-08. Maintained during the Dependabot clean-up pass; update when the state changes._

## Configuration

- Ecosystems covered: composer (`/`), npm (`/`), docker (`/docker`), github-actions (`/`).
- Grouping: `minor-and-patch` for every ecosystem (open-PR limit 5 each).
- Schedule: weekly.
- Ignore rules: composer: `inertiajs/inertia-laravel` majors (paired with `@inertiajs/vue3`, which also ignores majors); npm: `eslint` majors (eslint-plugin-vue 9 peers eslint <= 9), `eslint-plugin-vue` majors (10 drops the legacy eslintrc presets), `tailwindcss` majors (4 moves the PostCSS plugin), `@inertiajs/vue3` majors.

## State at last update

- Open Dependabot PRs: 0 (each merged or closed only after reading its checks).
- Default-branch CI: green at last check.

## Time-limited exemptions

- `osv-scanner.toml`: `GHSA-vfj7-8cjw-p6xm` (braces, no patched version, dev-only via the frontend toolchain), `ignoreUntil` 2026-11-15.

## Notes

- `package.json` carries an `overrides` entry pinning `postcss-selector-parser` to `^7.1.6` to clear GHSA-rj75-hqrm-r3gf; drop it once upstream ranges allow the fixed version.

## Deferred (not re-raised each pass)

- Ignored major versions are listed in `.github/dependabot.yml` with the reason for each.
- Re-check exemptions before their `effectiveUntil` date (2026-11-15) and drop them once upstream fixes ship.
