<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    use HasFactory;

    protected $table = 'system_alerts';

    protected $fillable = [
        'kind',
        'severity',
        'message',
        'context',
        'observed_at',
        'resolved_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'observed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
