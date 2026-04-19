<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGradeItemRequest;
use App\Http\Requests\UpdateGradeItemRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\GradeItem;
use App\Models\Section;
use App\Services\GradeItemService;
use Illuminate\Http\JsonResponse;

final class GradeItemController extends Controller
{
    public function __construct(
        private readonly GradeItemService $gradeItemService,
    ) {
    }

    public function index(Section $section): JsonResponse
    {
        $items = $this->gradeItemService->list($section);
        return ApiEnvelope::data($items);
    }

    public function store(CreateGradeItemRequest $request, Section $section): JsonResponse
    {
        $this->authorize('create', [GradeItem::class, $section]);

        $item = $this->gradeItemService->create($request->user(), $section, $request->validated());
        return ApiEnvelope::data($item, 201);
    }

    public function update(UpdateGradeItemRequest $request, Section $section, GradeItem $gradeItem): JsonResponse
    {
        $this->authorize('update', $gradeItem);

        $item = $this->gradeItemService->update($request->user(), $gradeItem, $request->validated());
        return ApiEnvelope::data($item);
    }

    public function publish(Section $section, GradeItem $gradeItem): JsonResponse
    {
        $this->authorize('update', $gradeItem);

        $item = $this->gradeItemService->publish(request()->user(), $gradeItem);
        return ApiEnvelope::data($item);
    }
}
