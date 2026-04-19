<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Appointment;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class AppointmentPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $appointment->owner_user_id === $user->id
            || $appointment->created_by === $user->id
            || $this->isStaff($user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->isStaff($user->id) || $appointment->created_by === $user->id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    private function isStaff(int $userId): bool
    {
        return $this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())
            || $this->scopeService->hasRole($userId, RoleName::Registrar);
    }
}
