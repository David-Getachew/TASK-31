<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Post;
use App\Models\Thread;
use App\Services\ContentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostController extends Controller
{
    public function __construct(
        private readonly ContentSubmissionService $contentService,
    ) {
    }

    public function index(Thread $thread): JsonResponse
    {
        $this->authorize('view', $thread);

        $posts = $thread->posts()->with('author')->orderBy('created_at')->paginate(50);
        return ApiEnvelope::data($posts);
    }

    public function store(CreatePostRequest $request, Thread $thread): JsonResponse
    {
        $this->authorize('view', $thread);
        $this->authorize('create', [Post::class, $thread]);

        $post = $this->contentService->createPost($request->user(), $thread, $request->validated());
        return ApiEnvelope::data($post, 201);
    }

    public function show(Thread $thread, Post $post): JsonResponse
    {
        if ($post->thread_id !== $thread->id) {
            abort(404);
        }

        $this->authorize('view', $thread);

        return ApiEnvelope::data($post->load(['author', 'comments.author']));
    }

    public function update(UpdatePostRequest $request, Thread $thread, Post $post): JsonResponse
    {
        if ($post->thread_id !== $thread->id) {
            abort(404);
        }

        $this->authorize('update', $post);

        $post = $this->contentService->updatePost($request->user(), $post, $request->validated());
        return ApiEnvelope::data($post);
    }

    public function destroy(Request $request, Thread $thread, Post $post): JsonResponse
    {
        if ($post->thread_id !== $thread->id) {
            abort(404);
        }

        $this->authorize('delete', $post);

        $this->contentService->deletePost($request->user(), $post);
        return ApiEnvelope::data(null, 204);
    }
}
