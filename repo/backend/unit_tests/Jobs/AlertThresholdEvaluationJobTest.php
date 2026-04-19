<?php

declare(strict_types=1);

use App\Jobs\AlertThresholdEvaluationJob;
use App\Enums\CircuitBreakerMode;
use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

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
