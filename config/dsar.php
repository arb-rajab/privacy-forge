<?php

return [
    // NFR-006: ≤3 submissions per subject identifier per 24h, configurable.
    'submission_rate_limit_per_day' => (int) env('DSAR_SUBMISSION_RATE_LIMIT_PER_DAY', 3),

    // NFR-007: signed status/export URLs must never exceed a 72-hour TTL.
    // Clamped here (not just documented) so a misconfigured env value can
    // never push a live link past the NFR's hard limit.
    'status_link_ttl_hours' => min((int) env('DSAR_STATUS_LINK_TTL_HOURS', 72), 72),
];
