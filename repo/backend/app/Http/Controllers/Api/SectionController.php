<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiEnvelope;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedIds = $this->resolveAllowedSectionIds($request->user());

        $sections = Section::with('course')
            ->when($allowedIds !== null, fn ($q) => $q->whereIn('id', $allowedIds))
            ->when($request->query('course_id'), fn ($q, $v) => $q->where('course_id', $v))
            ->orderBy('section_code')
            ->paginate(20);

        return ApiEnvelope::data($sections);
    }

    public function show(Section $section): JsonResponse
    {
        $this->authorize('view', $section);

        return ApiEnvelope::data($section->load('course'));
    }

    public function roster(Section $section): JsonResponse
    {
        $this->authorize('viewRoster', $section);

        $enrollments = $section->enrollments()->with('user')->paginate(50);
        return ApiEnvelope::data($enrollments);
    }

    /**
     * Returns null for admin/registrar (unrestricted), a populated array for grant/enrollment scope,
     * or an empty array for strict denial when the user has neither grants nor active enrollments.
     *
     * @return int[]|null
     */
    private function resolveAllowedSectionIds(User $user): ?array
    {
        if ($this->isAdminOrRegistrar($user)) {
            return null;
        }

        $granted = $user->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->where('scope_type', ScopeType::Section->value)
            ->whereNull('revoked_at')
            ->pluck('scope_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count($granted) > 0) {
            return $granted;
        }

        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->pluck('section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count($enrolled) > 0) {
            return $enrolled;
        }

        return [];
    }

    private function isAdminOrRegistrar(User $user): bool
    {
        return $user->roleAssignments()
            ->whereHas('role', fn ($q) => $q->whereIn('name', [RoleName::Administrator->value, RoleName::Registrar->value]))
            ->whereNull('revoked_at')
            ->exists();
    }
}
