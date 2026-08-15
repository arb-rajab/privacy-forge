<?php

use App\Services\ConnectorSignatureService;

test('verify accepts a signature produced by sign for the same secret/timestamp/body', function () {
    $service = new ConnectorSignatureService;
    $signature = $service->sign('shared-secret', '1700000000', '{"status":"success"}');

    expect($service->verify('shared-secret', '1700000000', '{"status":"success"}', $signature))->toBeTrue();
});

test('verify rejects a signature computed with a different secret', function () {
    $service = new ConnectorSignatureService;
    $signature = $service->sign('shared-secret', '1700000000', '{"status":"success"}');

    expect($service->verify('a-different-secret', '1700000000', '{"status":"success"}', $signature))->toBeFalse();
});

test('verify rejects a signature whose body was tampered with after signing', function () {
    $service = new ConnectorSignatureService;
    $signature = $service->sign('shared-secret', '1700000000', '{"status":"success"}');

    expect($service->verify('shared-secret', '1700000000', '{"status":"failed"}', $signature))->toBeFalse();
});

test('isTimestampFresh accepts a timestamp within the tolerance window, in either direction', function () {
    $service = new ConnectorSignatureService;

    expect($service->isTimestampFresh((string) (time() - 100), 300))->toBeTrue();
    expect($service->isTimestampFresh((string) (time() + 100), 300))->toBeTrue();
});

test('isTimestampFresh rejects a timestamp outside the tolerance window', function () {
    $service = new ConnectorSignatureService;

    expect($service->isTimestampFresh((string) (time() - 301), 300))->toBeFalse();
});

test('isTimestampFresh rejects a non-numeric timestamp outright, rather than casting it to 0', function () {
    $service = new ConnectorSignatureService;

    expect($service->isTimestampFresh('not-a-number', 300))->toBeFalse();
});
