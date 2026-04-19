<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Enrollment;
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
        return $this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->hasRole($user->id, RoleName::Registrar);
    }
}