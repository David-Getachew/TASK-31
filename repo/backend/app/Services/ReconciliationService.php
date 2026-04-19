<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReconciliationStatus;
use App\Models\ReconciliationFlag;
use App\Models\User;
use App\Support\AuditLogger;
use CampusLearn\Support\Exceptions\InvalidStateTransition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ReconciliationService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function openFlags(int $perPage = 20): LengthAwarePaginator
    {
        return ReconciliationFlag::where('status', ReconciliationStatus::Open)
            ->orderBy('source_type')
            ->paginate($perPage);
    }

    public function resolve(User $actor, ReconciliationFlag $flag, string $notes): ReconciliationFlag
    {
        return DB::transaction(function () use ($actor, $flag, $notes): ReconciliationFlag {
            if ($flag->status === ReconciliationStatus::Resolved) {
                throw new InvalidStateTransition('reconciliation', ReconciliationStatus::Resolved->value, 'resolve');
            }

            $flag->update([
                'status'      => ReconciliationStatus::Resolved,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'notes'       => $notes,
            ]);

            $this->audit->record($actor->id, 'reconciliation.resolved', 'reconciliation_flag', $flag->id, [
                'source_type' => $flag->source_type->value,
                'source_id'   => $flag->source_id,
            ]);

            return $flag->fresh();
        });
    }

    public function summary(): array
    {
        $open     = ReconciliationFlag::where('status', ReconciliationStatus::Open)->count();
        $resolved = ReconciliationFlag::where('status', ReconciliationStatus::Resolved)->count();

        $byType = ReconciliationFlag::where('status', ReconciliationStatus::Open)
            ->selectRaw('source_type, COUNT(*) as count')
            ->groupBy('source_type')
            ->pluck('count', 'source_type')
            ->all();

        return [
            'open_count'     => $open,
            'resolved_count' => $resolved,
            'open_by_type'   => $byType,
        ];
    }
}
