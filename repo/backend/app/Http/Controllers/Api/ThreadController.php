<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateThreadRequest;
use App\Http\Requests\UpdateThreadRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Thread;
use App\Services\ContentSubmissionService;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ThreadController extends Controller
{
    public function __construct(
        private readonly ContentSubmissionService $contentService,
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Thread::class);

        $user = $request->user();

        $isGlobalAdmin = $this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global());
        $isGlobalRegistrar = $this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global());
        $isTeacher     = $this->scopeService->hasRole($user->id, RoleName::Teacher);

        $threads = Thread::with('author')
            ->when(!$isGlobalAdmin && !$isGlobalRegistrar, function ($q) use ($user, $isTeacher) {
                $registrarTermIds = $user->roleAssignments()
                    ->whereHas('role', fn ($r) => $r->where('name', RoleName::Registrar->value))
                    ->whereNull('revoked_at')
                    ->where('scope_type', ScopeType::Term->value)
                    ->pluck('scope_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $teacherScope = $isTeacher ? $this->scopeService->teacherScopeIds($user->id) : null;

                $enrolledSectionIds = Enrollment::where('user_id', $user->id)
                    ->where('status', EnrollmentStatus::Enrolled)
                    ->pluck('section_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $q->where(function ($sub) use ($registrarTermIds, $teacherScope, $enrolledSectionIds) {
                    $matched = false;
                    if (!empty($registrarTermIds)) {
                        $courseIds = Course::whereIn('term_id', $registrarTermIds)->pluck('id')->all();
                        $sub->orWhereIn('course_id', $courseIds);
                        $matched = true;
                    }
                    if ($teacherScope !== null) {
                        if ($teacherScope['global']) {
                            $sub->orWhereRaw('1=1');
                            $matched = true;
                        } else {
                            if (!empty($teacherScope['section_ids'])) {
                                $sub->orWhereIn('section_id', $teacherScope['section_ids']);
                                $matched = true;
                            }
                            if (!empty($teacherScope['course_ids'])) {
                                $sub->orWhereIn('course_id', $teacherScope['course_ids']);
                                $matched = true;
                            }
                        }
                    }
                    if (!empty($enrolledSectionIds)) {
                        $sub->orWhereIn('section_id', $enrolledSectionIds);
                        $matched = true;
                    }
                    if (!$matched) {
                        $sub->whereRaw('1=0');
                    }
                });
            })
            ->when($request->query('section_id'), fn ($q, $v) => $q->where('section_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiEnvelope::data($threads);
    }

    public function store(CreateThreadRequest $request): JsonResponse
    {
        $section = Section::findOrFail((int) $request->validated('section_id'));
        $this->authorize('create', [Thread::class, $section]);

        $thread = $this->contentService->createThread($request->user(), $request->validated());
        return ApiEnvelope::data($thread, 201);
    }

    public function show(Thread $thread): JsonResponse
    {
        $this->authorize('view', $thread);

        return ApiEnvelope::data($thread->load(['author', 'posts.author']));
    }

    public function update(UpdateThreadRequest $request, Thread $thread): JsonResponse
    {
        $this->authorize('update', $thread);

        $thread = $this->contentService->updateThread($request->user(), $thread, $request->validated());
        return ApiEnvelope::data($thread);
    }
}
