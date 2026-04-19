<?php

namespace App\Models;

use App\Enums\ReconciliationSourceType;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationFlag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'source_type',
        'source_id',
        'status',
        'resolved_by',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'source_type' => ReconciliationSourceType::class,
        'status' => ReconciliationStatus::class,
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
