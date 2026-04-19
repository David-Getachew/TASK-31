<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\BillType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bill_schedule_id',
        'type',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'paid_cents',
        'refunded_cents',
        'status',
        'issued_on',
        'due_on',
        'past_due_at',
        'paid_at',
    ];

    protected $casts = [
        'type' => BillType::class,
        'status' => BillStatus::class,
        'issued_on' => 'date',
        'due_on' => 'date',
        'past_due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BillSchedule::class, 'bill_schedule_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function penaltyJobs(): HasMany
    {
        return $this->hasMany(PenaltyJob::class);
    }

    public function scopePastDue(Builder $query): Builder
    {
        return $query->whereIn('status', [BillStatus::Open, BillStatus::Partial, BillStatus::PastDue])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', now()->toDateString());
    }
}
