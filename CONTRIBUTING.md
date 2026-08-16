# Contributing

This is primarily a solo portfolio project built through a documented,
session-based workflow (see `docs/project-memory/`). External contributions
are welcome once the project reaches a stable v1.0.0, but please read this
first.

## Before contributing

1. Check `docs/project-memory/01-scope-and-non-goals.md` — pull requests for
   explicit non-goals will be declined regardless of quality.
2. Check `docs/project-memory/11-backlog.md` for planned work to avoid
   duplicate effort.
3. Open an issue before a large PR — small fixes (typos, obvious bugs) can go
   straight to a PR.

## Workflow

- **Branching:** trunk-based. Branch from `main` as `feat/<issue>-<slug>`,
  `fix/…`, `chore/…`, or `sec/…`.
- **Commits:** [Conventional Commits](https://www.conventionalcommits.org/)
  (`feat:`, `fix:`, `docs:`, `test:`, `ci:`, `refactor:`, `chore:`, `perf:`,
  `sec:`). Enforced by commitlint in CI.
- **Pull requests:** must link an issue (`Closes #N`), pass CI (lint,
  static analysis, tests, security scans), and use the PR template.
- **Code review:** all PRs require passing CI at minimum; the maintainer
  reviews for architecture and security fit.

## Development setup

**Requirements:** Docker and Docker Compose. Nothing else needs to be
installed on your host machine — PHP, Node, PostgreSQL, Redis, and MinIO
all run in containers.

```bash
git clone https://github.com/arb-rajab/privacy-forge.git
cd privacy-forge
cp .env.example .env
docker compose up --build
```

On first run, the `app` container installs PHP dependencies via Composer
and generates `composer.lock`; the `frontend` container installs npm
dependencies and generates `package-lock.json`. **Both lock files should be
committed** once generated — see the note in `.gitignore`. Neither exists
yet as of Session 5, because generating them requires real internet access
to Packagist and the npm registry, which the AI session that built this
scaffold did not have; this is recorded honestly in the Session 5 handoff
rather than faked.

Once containers are healthy:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The application is then available at `http://localhost:8000`, and the Vite
dev server (for frontend hot-reload) at `http://localhost:5173`.

**Health check:** `curl http://localhost:8000/up` should return `200`. If it
doesn't, `docker compose logs app` is the first place to look.

**Running tests, lint, and static analysis locally** (matches CI exactly):

```bash
docker compose exec app composer test      # Pest
docker compose exec app composer test:e2e  # Pest Browser Testing (consent widget + DSAR portal, tests/Browser/)
docker compose exec app composer lint      # Pint (add --fix to auto-fix)
docker compose exec app composer analyse   # Larastan / PHPStan, level 8
docker compose exec frontend npm run lint
```

**Validating the API contract** (`docs/architecture/openapi.yaml`) after
changing it:

```bash
pip install openapi-spec-validator
python -m openapi_spec_validator docs/architecture/openapi.yaml
```

## Reporting security issues

See [`SECURITY.md`](SECURITY.md) — do not use public issues for
vulnerabilities.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
