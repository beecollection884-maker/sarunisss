<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\ClearsDashboardCaches;

class SchoolClass extends Model
{
    use HasFactory, ClearsDashboardCaches;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'level',
        'academic_year',
        'homeroom_teacher_id',
        'description',
    ];

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)->withTimestamps();
    }

    public function classAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class);
    }
}
