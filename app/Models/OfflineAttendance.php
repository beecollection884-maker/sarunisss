<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAttendance extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'offline_device_id',
        'student_id',
        'teacher_id',
        'school_class_id',
        'teaching_assignment_id',
        'attendance_type',
        'attendance_date',
        'status',
        'notes',
        'recorded_at',
        'synced',
        'synced_at',
        'sync_error',
        'uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'recorded_at' => 'datetime',
            'synced_at' => 'datetime',
            'synced' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->uuid) {
                $model->uuid = \Illuminate\Support\Str::uuid();
            }
            if (! $model->recorded_at) {
                $model->recorded_at = now();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * Get unsynchronized records
     */
    public static function unsynced()
    {
        return static::where('synced', false);
    }

    /**
     * Get by device
     */
    public static function byDevice($deviceId)
    {
        return static::where('offline_device_id', $deviceId);
    }

    /**
     * Mark as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            'synced' => true,
            'synced_at' => now(),
            'sync_error' => null,
        ]);
    }

    /**
     * Mark sync error
     */
    public function recordSyncError(string $error): void
    {
        $this->update([
            'synced' => false,
            'sync_error' => $error,
        ]);
    }
}
