<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Comment;
use App\Models\Post;
use App\Services\ContentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommentController extends Controller
{
    public function __construct(
        private readonly ContentSubmissionService $contentService,
    ) {
    }

    public function store(CreateCommentRequest $request, Post $post): JsonResponse
    {
        $this->authorize('create', [Comment::class, $post]);

        $comment = $this->contentService->createComment($request->user(), $post, $request->validated());
        return ApiEnvelope::data($comment, 201);
    }

    public function update(UpdateCommentRequest $request, Post $post, Comment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            abort(404);
        }

        $this->authorize('update', $comment);

        $comment = $this->contentService->updateComment($request->user(), $comment, $request->validated());
        return ApiEnvelope::data($comment);
    }

    public function destroy(Request $request, Post $post, Comment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            abort(404);
        }

        $this->authorize('delete', $comment);

        $this->contentService->deleteComment($request->user(), $comment);
        return ApiEnvelope::data(null, 204);
    }
}
