<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiEnvelope;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LedgerEntry::class);

        $filters = array_filter([
            'user_id'    => $request->query('user_id'),
            'bill_id'    => $request->query('bill_id'),
            'order_id'   => $request->query('order_id'),
            'entry_type' => $request->query('entry_type'),
        ]);

        $entries = $this->ledgerService->list($filters);
        return ApiEnvelope::data($entries);
    }
}
