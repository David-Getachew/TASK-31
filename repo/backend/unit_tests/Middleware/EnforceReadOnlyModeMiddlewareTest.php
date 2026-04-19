<?php

declare(strict_types=1);

use App\Enums\CircuitBreakerMode;
use App\Http\Middleware\EnforceReadOnlyModeMiddleware;
use App\Services\CircuitBreakerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('allows safe methods while circuit is read-only', function () {
    $service = Mockery::mock(CircuitBreakerService::class);
    $middleware = new EnforceReadOnlyModeMiddleware($service);

    $request = Request::create('/api/v1/orders', 'GET');
    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('blocks write methods when circuit is read-only', function () {
    $service = Mockery::mock(CircuitBreakerService::class);
    $service->shouldReceive('currentMode')->once()->andReturn(CircuitBreakerMode::ReadOnly);

    $middleware = new EnforceReadOnlyModeMiddleware($service);
    $request = Request::create('/api/v1/orders', 'POST');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(503);
    expect((string) $response->getContent())->toContain('SERVICE_UNAVAILABLE');
});

it('allows write methods when circuit is read-write', function () {
    $service = Mockery::mock(CircuitBreakerService::class);
    $service->shouldReceive('currentMode')->once()->andReturn(CircuitBreakerMode::ReadWrite);

    $middleware = new EnforceReadOnlyModeMiddleware($service);
    $request = Request::create('/api/v1/orders', 'POST');

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});
