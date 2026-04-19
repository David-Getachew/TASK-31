<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Refund;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class RefundOperatorPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Refund $refund): bool
    {
        return $refund->bill->user_id === $user->id || $this->isOperator($user->id);
    }

    public function create(User $user): bool
    {
        return $this->isOperator($user->id);
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $this->isOperator($user->id);
    }

    public function reject(User $user, Refund $refund): bool
    {
        return $this->isOperator($user->id);
    }

    private function isOperator(int $userId): bool
    {
        return $this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->canPerform($userId, RoleName::Registrar, ScopeContext::global());
    }
}
