<?php

declare(strict_types=1);

use App\Jobs\AlertThresholdEvaluationJob;
use App\Enums\CircuitBreakerMode;
use App\Models\RequestMetric;
use App\Models\SystemAlert;
use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

afterEach(function () {
    app()->forgetInstance(CircuitBreakerService::class);
});

test('handle calls CircuitBreakerService::evaluate', function () {
    $serviceMock = Mockery::mock(CircuitBreakerService::class);
    $serviceMock->shouldReceive('evaluate')->once()->andReturn(CircuitBreakerMode::ReadWrite);

    app()->instance(CircuitBreakerService::class, $serviceMock);

    (new AlertThresholdEvaluationJob())->handle(app(CircuitBreakerService::class));
});

test('campuslearn:health:evaluate-circuit command is registered and callable', function () {
    $exitCode = Artisan::call('campuslearn:health:evaluate-circuit');
    expect($exitCode)->toBe(0);
});

test('circuit breaker trip emits a durable SystemAlert record', function () {
    // Defaults: trip=200 bps, reset=100 bps, minimum sample=20.
    // Create 20 OK + 10 errors (30 total, ~3333 bps error rate).
    for ($i = 0; $i < 20; $i++) {
        RequestMetric::create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'route'          => '/api/v1/health/circuit',
            'method'         => 'GET',
            'status'         => 200,
            'duration_ms'    => 10,
            'created_at'     => now(),
        ]);
    }
    for ($i = 0; $i < 10; $i++) {
        RequestMetric::create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'route'          => '/api/v1/health/circuit',
            'method'         => 'GET',
            'status'         => 500,
            'duration_ms'    => 10,
            'created_at'     => now(),
        ]);
    }

    $service = app(CircuitBreakerService::class);
    $mode = $service->evaluate();

    expect($mode)->toBe(CircuitBreakerMode::ReadOnly);
    $alert = SystemAlert::where('kind', 'circuit.tripped')->latest('id')->first();
    expect($alert)->not->toBeNull();
    expect($alert->severity)->toBe('critical');
    expect($alert->context['to_mode'] ?? null)->toBe('read_only');
    expect($alert->observed_at)->not->toBeNull();
});

test('no duplicate alert emitted when circuit mode is unchanged', function () {
    // With no metrics the window is empty and the sample minimum is not met,
    // so evaluate() returns the existing ReadWrite mode both times with no transition.
    $service = app(CircuitBreakerService::class);
    $service->evaluate();
    $service->evaluate();

    expect(SystemAlert::count())->toBe(0);
});
