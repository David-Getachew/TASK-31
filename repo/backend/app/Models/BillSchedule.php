<?php

namespace App\Models;

use App\Enums\BillScheduleSourceType;
use App\Enums\BillScheduleStatus;
use App\Enums\BillScheduleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'schedule_type',
        'amount_cents',
        'fee_category_id',
        'start_on',
        'end_on',
        'status',
        'next_run_on',
    ];

    protected $casts = [
        'source_type' => BillScheduleSourceType::class,
        'schedule_type' => BillScheduleType::class,
        'status' => BillScheduleStatus::class,
        'start_on' => 'date',
        'end_on' => 'date',
        'next_run_on' => 'date',
    ];

    protected function setScheduleTypeAttribute(mixed $value): void
    {
        // Backward compatibility for older fixtures/tests using "monthly".
        if ($value === 'monthly') {
            $value = BillScheduleType::RecurringMonthly->value;
        }

        $this->attributes['schedule_type'] = $value instanceof BillScheduleType
            ? $value->value
            : $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function scopeActiveSchedulesDueToday(Builder $query): Builder
    {
        return $query->where('status', BillScheduleStatus::Active)
            ->whereNotNull('next_run_on')
            ->where('next_run_on', '<=', now()->toDateString());
    }
}
