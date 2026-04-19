<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['term_id', 'code', 'title', 'description', 'status'];

    protected $casts = [
        'status' => CourseStatus::class,
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }
}
