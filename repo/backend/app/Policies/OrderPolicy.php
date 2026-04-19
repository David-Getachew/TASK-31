<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class OrderPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $this->isStaff($user->id);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->isStaff($user->id);
    }

    public function complete(User $user, Order $order): bool
    {
        return $this->isStaff($user->id);
    }

    public function delete(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $this->isStaff($user->id);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $this->isStaff($user->id);
    }

    private function isStaff(int $userId): bool
    {
        return $this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->canPerform($userId, RoleName::Registrar, ScopeContext::global());
    }
}
