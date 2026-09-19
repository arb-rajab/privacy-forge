<?php

return [
    // T-03 (06-security-threat-model.md): IP-level rate limiting on the
    // public consent capture/withdraw endpoints, distinct from the
    // per-subject DSAR limit in NFR-006. These are widget-embeddable,
    // unauthenticated endpoints — the limit exists to blunt volumetric
    // abuse, not to constrain a legitimate integrator (a real page issues
    // at most a handful of capture/withdraw calls per visitor per minute).
    'capture_rate_limit_per_minute' => (int) env('CONSENT_CAPTURE_RATE_LIMIT_PER_MINUTE', 30),
    'withdraw_rate_limit_per_minute' => (int) env('CONSENT_WITHDRAW_RATE_LIMIT_PER_MINUTE', 30),
];
