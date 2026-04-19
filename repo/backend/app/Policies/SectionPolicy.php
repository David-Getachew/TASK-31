<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class SectionPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function view(User $user, Section $section): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        $ancestry = [
            'term'    => $section->term_id,
            'course'  => $section->course_id,
            'section' => $section->id,
        ];
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($section->term_id),
            $ancestry,
        )) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::section($section->id),
            $ancestry,
        )) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::course($section->course_id),
            $ancestry,
        )) {
            return true;
        }
        return Enrollment::where('user_id', $user->id)
            ->where('section_id', $section->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->exists();
    }

    public function viewRoster(User $user, Section $section): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        $ancestry = [
            'term'    => $section->term_id,
            'course'  => $section->course_id,
            'section' => $section->id,
        ];
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($section->term_id),
            $ancestry,
        )) {
            return true;
        }
        return $this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::section($section->id),
            $ancestry,
        );
    }
}
