<?php

declare(strict_types=1);

use App\Http\Middleware\IdempotencyMiddleware;
use CampusLearn\Billing\Contracts\IdempotencyKeyStore;
use CampusLearn\Billing\IdempotencyService;
use CampusLearn\Billing\StoredIdempotencyResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

function inMemoryIdempotencyStore(): IdempotencyKeyStore
{
    return new class implements IdempotencyKeyStore {
        /** @var array<string, StoredIdempotencyResult> */
        private array $rows = [];

        public function find(string $scope, string $keyHash): ?StoredIdempotencyResult
        {
            return $this->rows["{$scope}:{$keyHash}"] ?? null;
        }

        public function store(
            string $scope,
            string $keyHash,
            string $requestFingerprint,
            int $resultStatus,
            array $resultBody,
            int $ttlHours,
        ): StoredIdempotencyResult {
            $result = new StoredIdempotencyResult(
                scope: $scope,
                keyHash: $keyHash,
                requestFingerprint: $requestFingerprint,
                resultStatus: $resultStatus,
                resultBody: $resultBody,
            );
            $this->rows["{$scope}:{$keyHash}"] = $result;
            return $result;
        }
    };
}

it('returns 400 when idempotency key is missing', function () {
    $service = new IdempotencyService(inMemoryIdempotencyStore());
    $middleware = new IdempotencyMiddleware($service);

    $request = Request::create('/api/v1/orders/1/payment', 'POST', ['method' => 'cash']);
    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(400);
    expect((string) $response->getContent())->toContain('IDEMPOTENCY_KEY_REQUIRED');
});

it('returns downstream body and replay header for successful execution', function () {
    $service = new IdempotencyService(inMemoryIdempotencyStore());

    $middleware = new IdempotencyMiddleware($service);
    $request = Request::create('/api/v1/orders/1/payment', 'POST', ['method' => 'cash'], [], [], [
        'HTTP_IDEMPOTENCY_KEY' => 'idem-key-1',
    ]);

    $response = $middleware->handle($request, fn () => new Response(json_encode(['data' => ['ok' => true]]) ?: '{}', 201));

    expect($response->getStatusCode())->toBe(201);
    expect($response->headers->get(IdempotencyMiddleware::REPLAY_HEADER))->toBe('false');
    expect((string) $response->getContent())->toContain('"ok":true');
});

it('returns 409 on idempotency replay conflict', function () {
    $service = new IdempotencyService(inMemoryIdempotencyStore());

    $middleware = new IdempotencyMiddleware($service);
    $request = Request::create('/api/v1/orders/1/payment', 'POST', ['method' => 'cash'], [], [], [
        'HTTP_IDEMPOTENCY_KEY' => 'idem-key-2',
    ]);

    // Prime cache with first payload
    $middleware->handle($request, fn () => new Response(json_encode(['data' => ['ok' => true]]) ?: '{}', 200));

    // Reuse same key with different payload to trigger conflict
    $conflictingRequest = Request::create('/api/v1/orders/1/payment', 'POST', ['method' => 'card'], [], [], [
        'HTTP_IDEMPOTENCY_KEY' => 'idem-key-2',
    ]);

    $response = $middleware->handle($conflictingRequest, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(409);
    expect((string) $response->getContent())->toContain('IDEMPOTENCY_KEY_CONFLICT');
});
