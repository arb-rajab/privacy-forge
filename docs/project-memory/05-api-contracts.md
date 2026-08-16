# API / Event Contracts
> Purpose: the interface others depend on.
> Project: privacy-forge (public)
> Last updated: 2026-08-17

## Style and rationale

**REST + OpenAPI 3.1**, not GraphQL or gRPC. This is a deliberate choice, not
a default: the API surface is small, resource-shaped (consent records, DSARs,
policies), and consumed primarily by this project's own admin SPA and a
small number of external connectors — none of the usual GraphQL motivations
(flexible client-driven querying across many consumers with differing needs)
apply, and gRPC's main benefit (efficient service-to-service binary
protocol) doesn't matter for a single-instance app talking to occasional
external webhooks over HTTP. REST/JSON is the least surprising choice for
connector authors who need to integrate against this contract without
tooling specific to this project.

The full machine-readable contract is `docs/architecture/openapi.yaml`
(OpenAPI 3.1, validated — see Validation below). This document explains the
contract; it does not duplicate every field.

## Authentication and authorisation model

- **Staff users:** session-based auth (Laravel's standard session
  middleware), scoped by role. Every sensitive action additionally passes
  through the `PolicyEvaluator` (ADR-0001) — session auth establishes *who
  you are*, ABAC evaluation decides *whether this specific action is
  allowed*. A `403` response from a sensitive-action endpoint always
  includes the `policy_id` that denied it (see `ProblemDetail` schema),
  which is what makes denials debuggable and auditable rather than opaque.
- **Data subjects:** no account exists. Access to a specific DSAR's status
  or export bundle is entirely via signed, time-limited tokens embedded in
  the URL — there is no username/password path for a data subject at all,
  deliberately, since requiring an account to exercise a GDPR right would
  itself be a poor experience for a one-off requester.
- **Connectors:** a distinct credential type (`connectorAuth` in the
  OpenAPI spec) — an HMAC signature over the request body and a timestamp,
  never the same credential space as staff sessions. This separation is
  intentional: a compromised connector credential must not grant any staff
  capability, and vice versa.

## Endpoints / schema summary

See `docs/architecture/openapi.yaml` for the authoritative, validated
contract. Grouped by tag:

- **Consent** (public): get current notice, capture consent, withdraw
  consent.
- **DSAR Portal** (public, signed-link-based): submit a DSAR, check status,
  download an export bundle.
- **Admin — Purposes and Policies** (staff, session-authenticated):
  identity verification, erasure approval (separation-of-duties enforced),
  retention dry-run.
- **Admin — RoPA and Audit** (staff): RoPA export, audit log query.
- **Connector Callback** (connector-authenticated): report task outcome.

Purpose and notice *creation* endpoints (`POST /admin/consent-purposes`,
`DELETE /admin/consent-purposes/{id}`, `POST
/admin/consent-purposes/{id}/notices`) were added mechanically at Session
6a, once the consent-capture slice's actual field needs were known, per
the plan described here. Retention-policy creation remains undesigned
until the retention slice is implemented.

`POST /dsar`, `GET /dsar/status/{signedToken}`, `POST
/admin/dsar/{dsarId}/verify-identity`, and `POST
/admin/dsar/{dsarId}/approve-erasure` were all present in the Session 3
draft spec and are implemented as specified — no contract changes were
needed for any of the four. Erasure approval (Session 7/6c) is gated by
the `dsar.erasure.approve` policy, whose separation-of-duties and
verified-before-approved conditions rely on a new `not_equals_attribute`
condition operator (ADR-0007) — see `12-session-handoff.md`.

`GET /dsar/export/{signedToken}/download` and `POST
/connector-callback/{taskId}` are implemented at Session 8, as specified
— `{signedToken}` is `EXPORT_BUNDLE.download_token` (T-05: unguessable,
never the row's own uuid), and the outbound/inbound webhook contracts
described below are implemented field-for-field as documented, including
`subject_identifier` and `schema_version` in the outbound payload.

`GET /admin/ropa/export` is implemented at Session 12 (US-013/FR-016), as
specified in the Session 3 draft — `format=pdf|csv`, gated by the new
`ropa.export` sensitive action (Owner or Privacy Manager). Generated on
demand (`App\Services\RopaGenerator`), not from a stored RoPA row — see
`09-decision-log.md`.

## Error model

**RFC 9457 Problem Details** (`application/json`, `type`/`title`/`status`/
`detail`) for every error response. Chosen for consistency with standard
tooling and because it gives ABAC denials a natural home for the
`policy_id` extension field, without inventing a bespoke error shape.

## Versioning and deprecation policy

- The API is versioned in the URL path (`/api/v1/...`). A breaking change
  requires a `/api/v2` path, not a silent behaviour change under `v1`.
- The connector webhook/callback contract (outbound half, below) is
  versioned independently via a `schema_version` field in the webhook
  payload, since connectors are external, third-party-operated systems that
  cannot be forced to upgrade in lockstep with the core application.

## Idempotency, pagination, rate limits

- **Idempotency:** every connector task carries a stable `task_id`; a
  connector receiving the same webhook twice (e.g. after a retry) must treat
  it as the same task, not create duplicate work. Consent capture is not
  idempotent by design — two capture events are two pieces of evidence, not
  a duplicate to be collapsed.
- **Pagination:** audit log queries (`/admin/audit-log`) are cursor-paginated
  once implemented (Session 6) — not included as a full parameter set in the
  Session 3 draft spec, since exact cursor shape depends on the final
  indexing decisions in `04-data-model.md`, which are settled but not yet
  implemented.
- **Rate limits:** DSAR submission is rate-limited per subject identifier
  (NFR-006, ≤3/24h) via Redis, returning `429` with a `ProblemDetail` body.
  Consent capture is not rate-limited per-subject (a subject may legitimately
  interact with many purposes), but is rate-limited at the IP level as a
  general abuse control (finalised in Session 4's threat model).

## Events published/consumed

**No internal event bus or message broker exists in this architecture**
(per the scalability decision in `03-architecture.md` — deliberately not
duplicating the event-driven pattern demonstrated elsewhere in the
portfolio, in R04 and R07). The only "event-shaped" integration point is the
connector webhook contract, which is request/response (with retries), not a
pub/sub event stream:

### Connector webhook contract (outbound: application → connector)

- **Trigger:** a DSAR is actioned (identity verified, and for erasure,
  approved per the separation-of-duties policy).
- **Delivery:** `POST` to the connector's registered `webhook_url`, signed
  with HMAC-SHA256 over the raw body + a timestamp header, secret shared at
  connector registration time.
- **Payload:** `{ task_id, dsar_id, task_type: "export"|"erasure",
  subject_identifier, schema_version }`.
- **Retry policy:** exponential backoff, configurable max attempts (default
  5); exhaustion marks the task `failed` (ADR-0004).

### Connector callback contract (inbound: connector → application)

- **Endpoint:** `POST /connector-callback/{taskId}` (see OpenAPI spec).
- **Auth:** `X-Connector-Signature` header, same HMAC scheme as the outbound
  webhook, validated against the registered connector's secret.
- **Payload:** `{ status: "success"|"failed"|"partial", failure_reason? }`.
- **Idempotency:** repeated callbacks for the same `task_id` with the same
  status are accepted as a no-op; a callback reporting a *different* status
  for an already-terminal task is rejected and logged as an anomaly (flagged
  forward to the Session 4 threat model — this is exactly the kind of signal
  that indicates either a bug or a compromised connector).

## Validation

```
$ python3 -m openapi_spec_validator docs/architecture/openapi.yaml
docs/architecture/openapi.yaml: OK
```

Run against `openapi-spec-validator` (OpenAPI 3.1). Re-run this check in CI
from Session 5 onward so the contract can never silently drift into an
invalid state as endpoints are added during implementation.
