<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\ClearsDashboardCaches;

class TeachingAssignment extends Model
{
    use HasFactory, ClearsDashboardCaches;

    protected static function booted(): void
    {
        // Panggil bootClearsDashboardCaches dari trait
        static::bootClearsDashboardCaches();

        static::saved(function (TeachingAssignment $assignment): void {
            static::clearScheduleCache($assignment);
        });

        static::deleted(function (TeachingAssignment $assignment): void {
            static::clearScheduleCache($assignment);
        });
    }

    protected static function clearScheduleCache(TeachingAssignment $assignment): void
    {
        // Hapus cache jadwal saat ini
        \Illuminate\Support\Facades\Cache::forget("class_schedule_{$assignment->school_class_id}_{$assignment->academic_year}");
        \Illuminate\Support\Facades\Cache::forget("teacher_schedule_{$assignment->teacher_id}_{$assignment->academic_year}");
        if ($assignment->substitute_teacher_id) {
            \Illuminate\Support\Facades\Cache::forget("teacher_schedule_{$assignment->substitute_teacher_id}_{$assignment->academic_year}");
        }

        // Hapus cache jadwal original jika data lama berubah
        $origClass = $assignment->getOriginal('school_class_id');
        $origYear = $assignment->getOriginal('academic_year');
        $origTeacher = $assignment->getOriginal('teacher_id');
        $origSubTeacher = $assignment->getOriginal('substitute_teacher_id');

        if ($origClass && $origYear) {
            \Illuminate\Support\Facades\Cache::forget("class_schedule_{$origClass}_{$origYear}");
        }
        if ($origTeacher && $origYear) {
            \Illuminate\Support\Facades\Cache::forget("teacher_schedule_{$origTeacher}_{$origYear}");
        }
        if ($origSubTeacher && $origYear) {
            \Illuminate\Support\Facades\Cache::forget("teacher_schedule_{$origSubTeacher}_{$origYear}");
        }
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'teacher_id',
        'subject_id',
        'school_class_id',
        'academic_year',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'substitute_teacher_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subjectAttendances(): HasMany
    {
        return $this->hasMany(SubjectAttendance::class);
    }
}
