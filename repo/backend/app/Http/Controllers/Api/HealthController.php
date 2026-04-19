<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\CircuitBreakerService;
use App\Services\RequestMetricsService;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class HealthController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly RequestMetricsService $metricsService,
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function index(): JsonResponse
    {
        $dbOk    = $this->checkDatabase();
        $queueOk = $this->checkQueue();
        $overall = $dbOk && $queueOk ? 'ok' : 'degraded';

        return response()->json([
            'status'  => $overall,
            'service' => 'campuslearn',
            'checks'  => [
                'database' => $dbOk ? 'ok' : 'error',
                'queue'    => $queueOk ? 'ok' : 'error',
            ],
        ], $overall === 'ok' ? 200 : 503);
    }

    public function circuit(): JsonResponse
    {
        $snapshot = $this->circuitBreaker->snapshot();
        return response()->json($snapshot);
    }

    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user || !$this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global()),
            403,
            'Administrator role required.'
        );

        $windowSeconds = (int) config('campuslearn.observability.circuit_window_seconds', 300);
        $summary    = $this->metricsService->summary($windowSeconds);
        $latency    = $this->metricsService->latencyPercentiles($windowSeconds);

        return response()->json(array_merge($summary, ['latency_ms' => $latency]));
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            Queue::size();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
