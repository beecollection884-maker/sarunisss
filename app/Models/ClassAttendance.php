<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\ClearsDashboardCaches;

class ClassAttendance extends Model
{
    use HasFactory, ClearsDashboardCaches;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'school_class_id',
        'student_id',
        'recorded_by_teacher_id',
        'attendance_date',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function setAttendanceDateAttribute(mixed $value): void
    {
        $this->attributes['attendance_date'] = $value instanceof CarbonInterface
            ? $value->toDateString()
            : (string) $value;
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'recorded_by_teacher_id');
    }
}
