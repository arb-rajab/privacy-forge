# Risk Register
> Purpose: known risks, owned and reviewed rather than forgotten
> Project: privacy-forge (public)
> Last updated: 2026-08-14

| ID | Risk | Category | Impact | Likelihood | Mitigation | Status | Review date |
|---|---|---|---|---|---|---|---|
| R-01 | Audit log lacks DB-level grant revocation (ADR-0003) — app's Postgres role owns the table, so privileges can't be revoked from itself | Security | Medium | Low (requires DB credential compromise) | Hash chain still provides tamper-evidence independently; needs a second, lower-privileged migration-only DB role to fully implement | Open | Before Session 8 (deployment) |

## Closed risks
