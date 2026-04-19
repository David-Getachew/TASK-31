<?php

declare(strict_types=1);

use App\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('adds correlation id when missing and mirrors it in response', function () {
    $middleware = new CorrelationIdMiddleware();
    $request = Request::create('/api/health', 'GET');

    $captured = null;
    $response = $middleware->handle($request, function (Request $req) use (&$captured): Response {
        $captured = $req->attributes->get(CorrelationIdMiddleware::CONTEXT_KEY);
        return new Response('ok', 200);
    });

    expect($captured)->not->toBeNull();
    expect($response->headers->get(CorrelationIdMiddleware::HEADER))->toBe($captured);
});

it('preserves valid inbound correlation id', function () {
    $middleware = new CorrelationIdMiddleware();
    $request = Request::create('/api/health', 'GET', [], [], [], [
        'HTTP_X_CORRELATION_ID' => 'd2146971-ee67-4ec0-8bca-3096fef2e2a2',
    ]);

    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->headers->get(CorrelationIdMiddleware::HEADER))->toBe('d2146971-ee67-4ec0-8bca-3096fef2e2a2');
});
