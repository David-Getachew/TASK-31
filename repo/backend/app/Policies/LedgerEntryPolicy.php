<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\LedgerEntry;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class LedgerEntryPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global());
    }

    public function view(User $user, LedgerEntry $ledgerEntry): bool
    {
        return $this->viewAny($user);
    }
}