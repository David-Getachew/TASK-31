<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'bill_id',
        'amount_cents',
        'reason_code_id',
        'operator_user_id',
        'status',
        'idempotency_key_id',
        'reversal_ledger_entry_id',
        'notes',
        'approved_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => RefundStatus::class,
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(RefundReasonCode::class, 'reason_code_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function idempotencyKey(): BelongsTo
    {
        return $this->belongsTo(IdempotencyKey::class);
    }

    public function reversalLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reversal_ledger_entry_id');
    }
}
