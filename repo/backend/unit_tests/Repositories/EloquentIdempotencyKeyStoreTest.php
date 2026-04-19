<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Repositories\EloquentIdempotencyKeyStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('idempotency key store persists and resolves active entries', function () {
    $store = new EloquentIdempotencyKeyStore();

    $stored = $store->store(
        'payment.complete:10',
        'key-hash-1',
        'fingerprint-1',
        201,
        ['data' => ['ok' => true]],
        24,
    );

    expect($stored->scope)->toBe('payment.complete:10');
    expect($stored->resultStatus)->toBe(201);

    $resolved = $store->find('payment.complete:10', 'key-hash-1');

    expect($resolved)->not->toBeNull();
    expect($resolved?->requestFingerprint)->toBe('fingerprint-1');
    expect($resolved?->resultBody)->toBe(['data' => ['ok' => true]]);
});

test('idempotency key store ignores expired entries', function () {
    IdempotencyKey::create([
        'scope' => 'orders.payment:1',
        'key_hash' => 'expired-key',
        'request_fingerprint' => 'fp',
        'result_status' => 200,
        'result_body' => ['data' => []],
        'expires_at' => now()->subHour(),
    ]);

    $store = new EloquentIdempotencyKeyStore();

    expect($store->find('orders.payment:1', 'expired-key'))->toBeNull();
});
