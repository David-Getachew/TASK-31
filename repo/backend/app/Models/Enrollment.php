<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $enrollment): void {
            if ($enrollment->enrolled_at === null) {
                $enrollment->enrolled_at = now();
            }

            if ($enrollment->status === EnrollmentStatus::Withdrawn && $enrollment->withdrawn_at === null) {
                $enrollment->withdrawn_at = now();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'section_id',
        'status',
        'enrolled_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'status' => EnrollmentStatus::class,
        'enrolled_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
