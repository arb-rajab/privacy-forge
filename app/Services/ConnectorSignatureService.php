<?php

namespace App\Services;

// ADR-0004: HMAC-SHA256 over "timestamp.body", shared by both directions
// of the connector contract — outbound webhook (Worker->Connector) and
// inbound callback (Connector->Worker), per T-07/T-08. A single service
// so the exact canonical string being signed can't drift between the two
// call sites.
class ConnectorSignatureService
{
    public function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        return hash_equals($this->sign($secret, $timestamp, $body), $signature);
    }

    // T-08: rejects replay of a validly-signed request outside a
    // tolerance window, regardless of signature validity.
    public function isTimestampFresh(string $timestamp, int $toleranceSeconds): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= $toleranceSeconds;
    }
}
