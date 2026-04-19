<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DrDrillRecord;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DrillRecordService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return DrDrillRecord::with('operator')
            ->orderByDesc('drill_date')
            ->paginate($perPage);
    }

    public function record(User $actor, array $data): DrDrillRecord
    {
        return DB::transaction(function () use ($actor, $data): DrDrillRecord {
            $drill = DrDrillRecord::create([
                'drill_date'       => $data['drill_date'],
                'operator_user_id' => $actor->id,
                'outcome'          => $data['outcome'],
                'notes'            => $data['notes'] ?? '',
            ]);

            $this->audit->record($actor->id, 'dr_drill.recorded', 'dr_drill_record', $drill->id, [
                'drill_date' => $data['drill_date'],
                'outcome'    => $data['outcome'],
            ]);

            return $drill->load('operator');
        });
    }
}
