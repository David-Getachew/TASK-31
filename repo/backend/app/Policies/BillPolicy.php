<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Bill;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class BillPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function view(User $user, Bill $bill): bool
    {
        return $bill->user_id === $user->id || $this->isFinanceStaff($user->id);
    }

    public function adminIndex(User $user): bool
    {
        return $this->isFinanceStaff($user->id);
    }

    public function adminGenerate(User $user): bool
    {
        return $this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global());
    }

    public function void(User $user, Bill $bill): bool
    {
        return $this->isFinanceStaff($user->id);
    }

    public function refund(User $user, Bill $bill): bool
    {
        return $this->isFinanceStaff($user->id);
    }

    private function isFinanceStaff(int $userId): bool
    {
        return $this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->canPerform($userId, RoleName::Registrar, ScopeContext::global());
    }
}
