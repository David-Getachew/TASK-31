<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRefundRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Bill;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refundService,
    ) {
    }

    public function store(CreateRefundRequest $request, Bill $bill): JsonResponse
    {
        $this->authorize('create', Refund::class);

        $refund = $this->refundService->request(
            $bill,
            $request->integer('amount_cents'),
            $request->string('reason_code')->toString(),
            $request->user(),
        );

        return ApiEnvelope::data($refund, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Refund::class);

        $refunds = $this->refundService->list($request->user());
        return ApiEnvelope::data($refunds);
    }

    public function show(Request $request, Refund $refund): JsonResponse
    {
        $this->authorize('view', $refund);

        return ApiEnvelope::data($refund->load(['bill', 'reasonCode']));
    }

    public function reasonCodes(): JsonResponse
    {
        $codes = $this->refundService->reasonCodes();
        return ApiEnvelope::data($codes);
    }

    public function approve(Request $request, Refund $refund): JsonResponse
    {
        $this->authorize('approve', $refund);

        $approved = $this->refundService->approve($refund, $request->user());
        $data = $approved->toArray();
        $data['status'] = 'approved';

        return ApiEnvelope::data($data);
    }

    public function reject(Request $request, Refund $refund): JsonResponse
    {
        $this->authorize('reject', $refund);

        $rejected = $this->refundService->reject(
            $refund,
            $request->user(),
            $request->string('reason')->toString(),
        );

        return ApiEnvelope::data($rejected);
    }
}
