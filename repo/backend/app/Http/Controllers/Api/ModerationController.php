<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModerationActionRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Thread;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModerationController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderationService,
    ) {
    }

    public function queue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Report::class);

        $threads = Thread::with(['author'])
            ->when($request->query('state'), fn ($q, $v) => $q->where('state', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiEnvelope::data($threads);
    }

    public function moderateThread(ModerationActionRequest $request, Thread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);

        $thread = $this->moderationService->apply(
            $request->user(),
            $thread,
            \App\Enums\ModerationActionType::from($request->string('action')->toString()),
            $request->string('notes')->toString(),
        );
        return ApiEnvelope::data($thread);
    }

    public function moderatePost(ModerationActionRequest $request, Post $post): JsonResponse
    {
        $this->authorize('moderate', $post);

        $post = $this->moderationService->apply(
            $request->user(),
            $post,
            \App\Enums\ModerationActionType::from($request->string('action')->toString()),
            $request->string('notes')->toString(),
        );
        return ApiEnvelope::data($post);
    }

    public function moderateComment(ModerationActionRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('moderate', $comment);

        $comment = $this->moderationService->apply(
            $request->user(),
            $comment,
            \App\Enums\ModerationActionType::from($request->string('action')->toString()),
            $request->string('notes')->toString(),
        );
        return ApiEnvelope::data($comment);
    }

    public function hideThread(Request $request, Thread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);

        $thread = $this->moderationService->apply(
            $request->user(),
            $thread,
            \App\Enums\ModerationActionType::Hide,
            $request->string('reason', '')->toString(),
        );
        return ApiEnvelope::data($thread);
    }

    public function restoreThread(Request $request, Thread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);

        $thread = $this->moderationService->apply(
            $request->user(),
            $thread,
            \App\Enums\ModerationActionType::Restore,
            $request->string('reason', '')->toString(),
        );
        return ApiEnvelope::data($thread);
    }

    public function lockThread(Request $request, Thread $thread): JsonResponse
    {
        $this->authorize('moderate', $thread);

        $thread = $this->moderationService->apply(
            $request->user(),
            $thread,
            \App\Enums\ModerationActionType::Lock,
            $request->string('reason', '')->toString(),
        );
        return ApiEnvelope::data($thread);
    }

    public function hidePost(Request $request, Thread $thread, Post $post): JsonResponse
    {
        abort_if($post->thread_id !== $thread->id, 404);
        $this->authorize('moderate', $post);

        $post = $this->moderationService->apply(
            $request->user(),
            $post,
            \App\Enums\ModerationActionType::Hide,
            $request->string('reason', '')->toString(),
        );
        return ApiEnvelope::data($post);
    }

    public function restorePost(Request $request, Thread $thread, Post $post): JsonResponse
    {
        abort_if($post->thread_id !== $thread->id, 404);
        $this->authorize('moderate', $post);

        $post = $this->moderationService->apply(
            $request->user(),
            $post,
            \App\Enums\ModerationActionType::Restore,
            $request->string('reason', '')->toString(),
        );
        return ApiEnvelope::data($post);
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Report::class);

        $log = ModerationAction::with(['moderator'])
            ->when($request->query('target_type'), fn ($q, $v) => $q->where('target_type', $v))
            ->when($request->query('target_id'), fn ($q, $v) => $q->where('target_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiEnvelope::data($log);
    }

    public function actionsLog(Request $request): JsonResponse
    {
        return $this->history($request);
    }
}
