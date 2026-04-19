<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\RosterImport;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;

final class RosterImportPolicy
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function viewAny(User $user, ?int $termId = null): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        if ($termId !== null) {
            return $this->scopeService->canPerform(
                $user->id,
                RoleName::Registrar,
                ScopeContext::term($termId),
                ['term' => $termId],
            );
        }
        return false;
    }

    public function view(User $user, RosterImport $import): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($import->term_id),
            ['term' => $import->term_id],
        )) {
            return true;
        }
        return $import->initiated_by === $user->id;
    }

    public function create(User $user, int $termId): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Registrar, ScopeContext::global())) {
            return true;
        }
        return $this->scopeService->canPerform(
            $user->id,
            RoleName::Registrar,
            ScopeContext::term($termId),
            ['term' => $termId],
        );
    }

}
