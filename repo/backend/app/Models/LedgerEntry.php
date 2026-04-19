<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            if (empty($entry->correlation_id)) {
                $entry->correlation_id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'bill_id',
        'order_id',
        'entry_type',
        'amount_cents',
        'description',
        'reference_entry_id',
        'correlation_id',
    ];

    protected $casts = [
        'entry_type' => LedgerEntryType::class,
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function referenceEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reference_entry_id');
    }
}
