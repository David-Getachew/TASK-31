<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class TermPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function view(User $user, Term $term): bool
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
            ScopeContext::term($term->id),
            ['term' => $term->id],
        )) {
            return true;
        }

        $courseIds = Course::where('term_id', $term->id)->pluck('id')->all();
        if (empty($courseIds)) {
            return false;
        }

        $teacherGrants = $user->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->whereNull('revoked_at')
            ->whereIn('scope_type', [ScopeType::Course->value, ScopeType::Section->value])
            ->get(['scope_type', 'scope_id']);

        foreach ($teacherGrants as $grant) {
            if ($grant->scope_type === ScopeType::Course->value && in_array((int) $grant->scope_id, $courseIds, true)) {
                return true;
            }
            if ($grant->scope_type === ScopeType::Section->value && $grant->scope_id !== null) {
                $section = Section::find($grant->scope_id, ['course_id']);
                if ($section !== null && in_array((int) $section->course_id, $courseIds, true)) {
                    return true;
                }
            }
        }

        return Enrollment::where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->whereHas('section', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->exists();
    }
}
