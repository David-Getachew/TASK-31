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
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TermController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedIds = $this->resolveAllowedTermIds($request->user());

        $terms = Term::query()
            ->when($allowedIds !== null, fn ($q) => $q->whereIn('id', $allowedIds))
            ->orderByDesc('starts_on')
            ->paginate(20);

        return ApiEnvelope::data($terms);
    }

    public function show(Term $term): JsonResponse
    {
        $this->authorize('view', $term);

        return ApiEnvelope::data($term);
    }

    /**
     * Returns null for admin/registrar (unrestricted), a populated array for grant/enrollment scope,
     * or an empty array for strict denial when the user has neither grants nor active enrollments.
     *
     * @return int[]|null
     */
    private function resolveAllowedTermIds(User $user): ?array
    {
        if ($this->isAdminOrRegistrar($user)) {
            return null;
        }

        // Tier 2: teacher explicit course/section grants → resolve to term IDs
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
            if (! empty($courseIds)) {
                return Course::whereIn('id', array_unique($courseIds))
                    ->whereNotNull('term_id')
                    ->pluck('term_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        // Tier 3: active enrollments → resolve to term IDs
        $sectionIds = Enrollment::where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->pluck('section_id')
            ->filter()
            ->all();

        if (! empty($sectionIds)) {
            $courseIds = Section::whereIn('id', $sectionIds)
                ->whereNotNull('course_id')
                ->pluck('course_id')
                ->filter()
                ->all();

            if (! empty($courseIds)) {
                return Course::whereIn('id', $courseIds)
                    ->whereNotNull('term_id')
                    ->pluck('term_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
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
