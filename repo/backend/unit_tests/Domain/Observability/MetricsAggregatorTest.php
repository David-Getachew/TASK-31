<?php

declare(strict_types=1);

namespace Tests\Domain\Observability;

use App\Models\RequestMetric;
use App\Services\RequestMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RequestMetric::query()->delete();
});

function seedMetrics(int $total, int $errors, int $ageMins = 1): void
{
    $ts = now()->subMinutes($ageMins);
    for ($i = 0; $i < $total; $i++) {
        RequestMetric::create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'route'          => '/api/v1/test',
            'method'         => 'GET',
            'status'         => $i < $errors ? 500 : 200,
            'duration_ms'    => 50 + $i,
            'user_id'        => null,
            'created_at'     => $ts,
        ]);
    }
}

test('zero metrics returns zero error rate', function () {
    $service  = new RequestMetricsService();
    $summary  = $service->summary(300);

    expect($summary['error_rate'])->toBe(0.0)
        ->and($summary['request_count'])->toBe(0);
});

test('error rate is calculated correctly', function () {
    seedMetrics(total: 100, errors: 5, ageMins: 1);

    $service  = new RequestMetricsService();
    $summary  = $service->summary(300);

    expect($summary['request_count'])->toBe(100)
        ->and($summary['error_count'])->toBe(5)
        ->and($summary['error_rate'])->toBe(5.0);
});

test('metrics outside window are excluded', function () {
    seedMetrics(total: 50, errors: 50, ageMins: 10); // outside 5-min window

    $service  = new RequestMetricsService();
    $summary  = $service->summary(300); // 300 seconds = 5 minutes

    expect($summary['request_count'])->toBe(0)
        ->and($summary['error_rate'])->toBe(0.0);
});

test('latency percentiles are computed from sorted durations', function () {
    // Create 10 metrics with durations 10, 20, ..., 100 ms
    for ($i = 1; $i <= 10; $i++) {
        RequestMetric::create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'route'          => '/test',
            'method'         => 'GET',
            'status'         => 200,
            'duration_ms'    => $i * 10,
            'user_id'        => null,
            'created_at'     => now()->subMinute(),
        ]);
    }

    $service     = new RequestMetricsService();
    $percentiles = $service->latencyPercentiles(300);

    expect($percentiles)->toHaveKeys(['p50', 'p95', 'p99'])
        ->and($percentiles['p50'])->toBeGreaterThan(0);
});
