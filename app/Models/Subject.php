<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\ClearsDashboardCaches;

class Subject extends Model
{
    use HasFactory, ClearsDashboardCaches;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'lesson_hours',
        'description',
        'day_of_week',
        'start_time',
        'end_time',
        'school_class_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lesson_hours' => 'integer',
            'day_of_week' => 'integer',
        ];
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class)->withTimestamps();
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class)->withTimestamps();
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
