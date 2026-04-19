<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class CoursePolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function view(User $user, Course $course): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($course->term_id),
            ['term' => $course->term_id],
        )) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::course($course->id),
            ['term' => $course->term_id, 'course' => $course->id],
        )) {
            return true;
        }

        $teacherSectionGrants = $user->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->whereNull('revoked_at')
            ->where('scope_type', ScopeType::Section->value)
            ->pluck('scope_id')
            ->filter()
            ->all();

        if (! empty($teacherSectionGrants)) {
            $courseIds = Section::whereIn('id', $teacherSectionGrants)->pluck('course_id')->all();
            if (in_array($course->id, array_map('intval', $courseIds), true)) {
                return true;
            }
        }

        return Enrollment::where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->exists();
    }
}
