<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CircuitBreakerMode;
use App\Models\CircuitBreakerState;
use App\Models\RequestMetric;
use App\Models\SystemAlert;
use CampusLearn\Observability\CircuitBreakerPolicy;
use CampusLearn\Observability\ErrorRateWindow;
use Illuminate\Support\Facades\Log;

class CircuitBreakerService
{
    public function __construct(
        private readonly CircuitBreakerPolicy $policy,
        private readonly int $windowSeconds,
    ) {
    }

    public function currentMode(): CircuitBreakerMode
    {
        return $this->state()->mode;
    }

    /**
     * Evaluate the current error rate and update circuit state if needed.
     * Called by the metrics middleware and health endpoints.
     */
    public function evaluate(): CircuitBreakerMode
    {
        $state  = $this->state();
        $window = $this->buildWindow();
        $newMode = $this->policy->evaluate($state->mode, $window);

        if ($newMode !== $state->mode) {
            $previousMode = $state->getOriginal('mode')?->value ?? $state->mode->value;
            $state->mode         = $newMode;
            $state->tripped_at   = $newMode === CircuitBreakerMode::ReadOnly ? now() : null;
            $state->tripped_reason = $newMode === CircuitBreakerMode::ReadOnly
                ? 'Error rate exceeded threshold'
                : null;
            $state->updated_at   = now();
            $state->save();

            Log::warning('Circuit breaker mode changed', [
                'from' => $previousMode,
                'to'   => $newMode->value,
            ]);

            $this->emitAlert($previousMode, $newMode, $window);
        }

        return $newMode;
    }

    /**
     * Persist a durable alert whenever the breaker transitions so ops tooling
     * has an audit-trail record in addition to ephemeral log output.
     */
    private function emitAlert(string $previousMode, CircuitBreakerMode $newMode, ErrorRateWindow $window): void
    {
        $tripped = $newMode === CircuitBreakerMode::ReadOnly;
        SystemAlert::create([
            'kind'     => $tripped ? 'circuit.tripped' : 'circuit.restored',
            'severity' => $tripped ? 'critical' : 'info',
            'message'  => $tripped
                ? 'Circuit breaker tripped to read-only due to elevated error rate.'
                : 'Circuit breaker restored to read-write.',
            'context'  => [
                'from_mode'       => $previousMode,
                'to_mode'         => $newMode->value,
                'error_rate_bps'  => $window->errorRateBps(),
                'total_requests'  => $window->totalRequests,
                'window_seconds'  => $window->windowSeconds,
            ],
            'observed_at' => now(),
            'resolved_at' => $tripped ? null : now(),
        ]);
    }

    /**
     * Returns the current state snapshot for health endpoint responses.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $state  = $this->state();
        $window = $this->buildWindow();

        return [
            'mode'            => $state->mode->value,
            'tripped_at'      => $state->tripped_at?->toIso8601String(),
            'tripped_reason'  => $state->tripped_reason,
            'error_rate_bps'  => $window->errorRateBps(),
            'total_requests'  => $window->totalRequests,
            'window_seconds'  => $window->windowSeconds,
        ];
    }

    private function state(): CircuitBreakerState
    {
        return CircuitBreakerState::firstOrCreate(
            ['id' => 1],
            [
                'mode'          => CircuitBreakerMode::ReadWrite,
                'tripped_at'    => null,
                'tripped_reason' => null,
                'updated_at'    => now(),
            ],
        );
    }

    private function buildWindow(): ErrorRateWindow
    {
        $since = now()->subSeconds($this->windowSeconds);

        $total  = RequestMetric::where('created_at', '>=', $since)->count();
        $errors = RequestMetric::where('created_at', '>=', $since)
            ->where('status', '>=', 500)
            ->count();

        return new ErrorRateWindow($total, $errors, $this->windowSeconds);
    }
}
