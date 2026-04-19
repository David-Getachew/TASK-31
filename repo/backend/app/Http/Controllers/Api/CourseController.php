<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiEnvelope;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedIds = $this->resolveAllowedCourseIds($request->user());

        $courses = Course::with('term')
            ->when($allowedIds !== null, fn ($q) => $q->whereIn('id', $allowedIds))
            ->when($request->query('term_id'), fn ($q, $v) => $q->where('term_id', $v))
            ->orderBy('code')
            ->paginate(20);

        return ApiEnvelope::data($courses);
    }

    public function show(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return ApiEnvelope::data($course->load('term'));
    }

    /**
     * Returns null for admin/registrar (unrestricted), a populated array for grant/enrollment scope,
     * or an empty array for strict denial when the user has neither grants nor active enrollments.
     *
     * @return int[]|null
     */
    private function resolveAllowedCourseIds(User $user): ?array
    {
        if ($this->isAdminOrRegistrar($user)) {
            return null;
        }

        // Tier 2: teacher explicit course/section grants → resolve to course IDs
        $teacherGrants = $user->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->whereNull('revoked_at')
            ->whereIn('scope_type', [ScopeType::Course->value, ScopeType::Section->value])
            ->get(['scope_type', 'scope_id']);

        if ($teacherGrants->isNotEmpty()) {
            $courseIds = [];
            foreach ($teacherGrants as $grant) {
                if ($grant->scope_type === ScopeType::Course->value && $grant->scope_id !== null) {
                    $courseIds[] = (int) $grant->scope_id;
                } elseif ($grant->scope_type === ScopeType::Section->value && $grant->scope_id !== null) {
                    $section = Section::find($grant->scope_id, ['course_id']);
                    if ($section !== null) {
                        $courseIds[] = (int) $section->course_id;
                    }
                }
            }
            return array_values(array_unique($courseIds));
        }

        // Tier 3: active enrollments → resolve to course IDs
        $sectionIds = Enrollment::where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->pluck('section_id')
            ->filter()
            ->all();

        if (! empty($sectionIds)) {
            return Section::whereIn('id', $sectionIds)
                ->whereNotNull('course_id')
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        // Tier 4: strict denial
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
