<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiEnvelope;
use App\Models\LedgerEntry;
use App\Models\ReconciliationFlag;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', LedgerEntry::class);

        $flags = $this->reconciliationService->openFlags();
        return ApiEnvelope::data($flags);
    }

    public function resolve(Request $request, ReconciliationFlag $reconciliationFlag): JsonResponse
    {
        $this->authorize('viewAny', LedgerEntry::class);

        $flag = $this->reconciliationService->resolve(
            $request->user(),
            $reconciliationFlag,
            $request->string('notes')->toString(),
        );
        return ApiEnvelope::data($flag);
    }

    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', LedgerEntry::class);

        $summary = $this->reconciliationService->summary();
        return ApiEnvelope::data($summary);
    }
}
