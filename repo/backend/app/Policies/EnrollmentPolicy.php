<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class EnrollmentPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }

        $section = $enrollment->section ?? Section::find($enrollment->section_id, ['term_id', 'course_id']);
        if ($section === null) {
            return false;
        }

        return $this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($section->term_id),
            ['term' => $section->term_id, 'course' => $section->course_id, 'section' => $enrollment->section_id],
        );
    }
}