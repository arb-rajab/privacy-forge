# Risk Register
> Purpose: known risks, owned and reviewed rather than forgotten
> Project: privacy-forge (public)
> Last updated: 2026-08-14

| ID | Risk | Category | Impact | Likelihood | Mitigation | Status | Review date |
|---|---|---|---|---|---|---|---|
| R-01 | Audit log lacks DB-level grant revocation (ADR-0003) — app's Postgres role owns the table, so privileges can't be revoked from itself | Security | Medium | Low (requires DB credential compromise) | Hash chain still provides tamper-evidence independently; needs a second, lower-privileged migration-only DB role to fully implement | Open | Before Session 8 (deployment) |
| R-02 | No seeding/bootstrap mechanism exists for `PolicyDefinition` rows — a fresh instance has no active `dsar.identity.verify` or `dsar.erasure.approve` policy until each is inserted manually (second row added Session 7/6c, same unresolved gap, not a new one) | Operability | Low (fails safe, not open — ADR-0006 fail-closed denies everyone rather than granting access; this is an availability/usability gap, not a security exposure) | High (true on every fresh install by default) | None yet; candidates are a `database/seeders/` bootstrap, or building the `policy.update` sensitive action (ADR-0006) and using it as the install-time step | Open | Before Session 8 (deployment) |

## Closed risks
