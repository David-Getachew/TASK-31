<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReportRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    public function storeForPost(CreateReportRequest $request, Post $post): JsonResponse
    {
        $this->authorize('create', Report::class);

        $report = $this->reportService->submit(
            $request->user(),
            'post',
            $post->id,
            $request->validated(),
        );
        return ApiEnvelope::data($report, 201);
    }

    public function storeForComment(CreateReportRequest $request, Post $post, Comment $comment): JsonResponse
    {
        abort_if($comment->post_id !== $post->id, 404);
        $this->authorize('create', Report::class);

        $report = $this->reportService->submit(
            $request->user(),
            'comment',
            $comment->id,
            $request->validated(),
        );
        return ApiEnvelope::data($report, 201);
    }
}
