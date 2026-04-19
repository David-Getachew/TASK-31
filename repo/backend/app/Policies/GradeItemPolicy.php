<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\GradeItem;
use App\Models\Section;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class GradeItemPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function create(User $user, ?Section $section = null): bool
    {
        return true;
    }

    public function update(User $user, GradeItem $gradeItem): bool
    {
        return true;
    }

    public function publish(User $user, GradeItem $gradeItem): bool
    {
        return true;
    }

    public function viewScores(User $user, GradeItem $gradeItem): bool
    {
        // Teachers see all scores; students see only their own (handled at controller level)
        return $this->isTeacherForSection($user->id, $gradeItem->section_id)
            || $this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global());
    }

    private function isTeacherForSection(int $userId, int $sectionId): bool
    {
        return $this->scopeService->canPerform(
            $userId,
            RoleName::Teacher,
            ScopeContext::section($sectionId),
        ) || $this->scopeService->canPerform(
            $userId,
            RoleName::Administrator,
            ScopeContext::global(),
        );
    }
}
