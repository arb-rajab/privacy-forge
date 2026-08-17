<?php

return [
    // ADR-0004: exponential backoff up to this many outbound webhook
    // delivery attempts before a DSAR_CONNECTOR_TASK is marked failed.
    'webhook_max_retry_attempts' => (int) env('CONNECTOR_WEBHOOK_MAX_RETRY_ATTEMPTS', 5),

    // T-08: callbacks signed outside this tolerance window (either side of
    // now) are rejected regardless of signature validity.
    'callback_signature_tolerance_seconds' => (int) env('CONNECTOR_CALLBACK_SIGNATURE_TOLERANCE_SECONDS', 300),

    // US-008/FR-010/NFR-007: clamped here the same way
    // config/dsar.php clamps its own status-link TTL — a misconfigured
    // env value can never push a live export URL past the NFR's hard
    // limit.
    'export_bundle_ttl_hours' => min((int) env('EXPORT_BUNDLE_SIGNED_URL_TTL_HOURS', 72), 72),

    // R-06 (docs/project-memory/10-risk-register.md): the base URL used
    // both (a) as the default webhook_url the reference/stub connector
    // is registered against (RegisterReferenceConnectorCommand), and (b)
    // by the reference connector's own outbound callback
    // (ReferenceConnectorWebhookController). Deliberately NOT
    // config('app.url') — that's http://localhost:8000 by default
    // (.env.example), which resolves to the calling container itself,
    // not the `app` service, so a webhook dispatched from the `worker`
    // container would never reach it. `app` is the docker-compose
    // service name, resolvable from any other container on the same
    // compose network.
    'reference_connector_base_url' => env('REFERENCE_CONNECTOR_BASE_URL', 'http://app:8000'),
];
