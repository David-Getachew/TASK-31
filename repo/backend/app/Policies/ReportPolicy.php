<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Report;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class ReportPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function resolve(User $user, Report $report): bool
    {
        return $this->isStaff($user->id);
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user->id);
    }

    private function isStaff(int $userId): bool
    {
        return $this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->canPerform($userId, RoleName::Registrar, ScopeContext::global())
            || $this->scopeService->canPerform($userId, RoleName::Teacher, ScopeContext::global());
    }
}
