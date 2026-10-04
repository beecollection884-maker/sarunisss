<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\OfflineAttendance;
use App\Models\Student;
use App\Models\SubjectAttendance;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * Service untuk mengelola absensi offline dan sync otomatis
 * 
 * Features:
 * - Record absensi tanpa internet
 * - Auto-sync ketika terhubung
 * - Conflict resolution
 * - Offline data management
 */
class OfflineAttendanceService
{
    /**
     * Record attendance in offline mode
     * 
     * @param array{
     *     offline_device_id: string,
     *     student_id: int,
     *     teacher_id: int,
     *     school_class_id: int,
     *     teaching_assignment_id?: int,
     *     attendance_type: string,
     *     attendance_date: string,
     *     status: string,
     *     notes?: string
     * } $data
     */
    public function recordOfflineAttendance(array $data): OfflineAttendance
    {
        return OfflineAttendance::create([
            'offline_device_id' => $data['offline_device_id'],
            'student_id' => $data['student_id'],
            'teacher_id' => $data['teacher_id'],
            'school_class_id' => $data['school_class_id'],
            'teaching_assignment_id' => $data['teaching_assignment_id'] ?? null,
            'attendance_type' => $data['attendance_type'],
            'attendance_date' => $data['attendance_date'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'recorded_at' => now(),
            'synced' => false,
        ]);
    }

    /**
     * Get unsynced attendance records
     */
    public function getUnsyncedRecords(int $limit = 100): Collection
    {
        return OfflineAttendance::unsynced()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unsynced records by device
     */
    public function getUnsyncedByDevice(string $deviceId, int $limit = 100): Collection
    {
        return OfflineAttendance::byDevice($deviceId)
            ->unsynced()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Sync offline attendance to online
     */
    public function syncAttendanceRecord(OfflineAttendance $offlineRecord): bool
    {
        try {
            // Check if record already exists
            if ($this->recordExists($offlineRecord)) {
                $offlineRecord->markAsSynced();
                return true;
            }

            // Create online record based on type
            if ($offlineRecord->attendance_type === 'class') {
                $this->createClassAttendance($offlineRecord);
            } else {
                $this->createSubjectAttendance($offlineRecord);
            }

            // Mark as synced
            $offlineRecord->markAsSynced();

            return true;
        } catch (\Exception $e) {
            $offlineRecord->recordSyncError($e->getMessage());
            return false;
        }
    }

    /**
     * Sync all unsynced records
     */
    public function syncAllRecords(int $limit = 100): array
    {
        $unsynced = $this->getUnsyncedRecords($limit);
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($unsynced as $record) {
            if ($this->syncAttendanceRecord($record)) {
                $synced++;
            } else {
                $failed++;
                $errors[] = [
                    'id' => $record->id,
                    'error' => $record->sync_error,
                ];
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'total' => $unsynced->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Sync records for specific device
     */
    public function syncDeviceRecords(string $deviceId, int $limit = 100): array
    {
        $unsynced = $this->getUnsyncedByDevice($deviceId, $limit);
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($unsynced as $record) {
            if ($this->syncAttendanceRecord($record)) {
                $synced++;
            } else {
                $failed++;
                $errors[] = [
                    'id' => $record->id,
                    'error' => $record->sync_error,
                ];
            }
        }

        return [
            'device_id' => $deviceId,
            'synced' => $synced,
            'failed' => $failed,
            'total' => $unsynced->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Check if record exists in online database
     */
    protected function recordExists(OfflineAttendance $offlineRecord): bool
    {
        if ($offlineRecord->attendance_type === 'class') {
            return ClassAttendance::where('student_id', $offlineRecord->student_id)
                ->where('school_class_id', $offlineRecord->school_class_id)
                ->where('attendance_date', $offlineRecord->attendance_date)
                ->exists();
        }

        return SubjectAttendance::where('student_id', $offlineRecord->student_id)
            ->where('teaching_assignment_id', $offlineRecord->teaching_assignment_id)
            ->where('attendance_date', $offlineRecord->attendance_date)
            ->exists();
    }

    /**
     * Create class attendance record
     */
    protected function createClassAttendance(OfflineAttendance $offlineRecord): ClassAttendance
    {
        return ClassAttendance::create([
            'school_class_id' => $offlineRecord->school_class_id,
            'student_id' => $offlineRecord->student_id,
            'recorded_by_teacher_id' => $offlineRecord->teacher_id,
            'attendance_date' => $offlineRecord->attendance_date,
            'status' => $offlineRecord->status,
            'notes' => $offlineRecord->notes,
        ]);
    }

    /**
     * Create subject attendance record
     */
    protected function createSubjectAttendance(OfflineAttendance $offlineRecord): SubjectAttendance
    {
        return SubjectAttendance::create([
            'teaching_assignment_id' => $offlineRecord->teaching_assignment_id,
            'student_id' => $offlineRecord->student_id,
            'recorded_by_teacher_id' => $offlineRecord->teacher_id,
            'attendance_date' => $offlineRecord->attendance_date,
            'status' => $offlineRecord->status,
            'notes' => $offlineRecord->notes,
        ]);
    }

    /**
     * Get offline attendance statistics
     */
    public function getStatistics(): array
    {
        $total = OfflineAttendance::count();
        $synced = OfflineAttendance::where('synced', true)->count();
        $unsynced = OfflineAttendance::where('synced', false)->count();
        $failed = OfflineAttendance::whereNotNull('sync_error')->count();

        return [
            'total' => $total,
            'synced' => $synced,
            'unsynced' => $unsynced,
            'failed' => $failed,
            'sync_rate' => $total > 0 ? round(($synced / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get device statistics
     */
    public function getDeviceStatistics(string $deviceId): array
    {
        $records = OfflineAttendance::byDevice($deviceId);
        $total = $records->count();
        $synced = $records->where('synced', true)->count();
        $unsynced = $records->where('synced', false)->count();

        return [
            'device_id' => $deviceId,
            'total' => $total,
            'synced' => $synced,
            'unsynced' => $unsynced,
            'sync_rate' => $total > 0 ? round(($synced / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Clear old synced records (older than X days)
     */
    public function clearOldSyncedRecords(int $days = 30): int
    {
        $cutoffDate = now()->subDays($days);

        return OfflineAttendance::where('synced', true)
            ->where('synced_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get attendance by student and date
     */
    public function getStudentAttendanceByDate(int $studentId, string $date): Collection
    {
        return OfflineAttendance::where('student_id', $studentId)
            ->where('attendance_date', $date)
            ->get();
    }

    /**
     * Get attendance by device and date range
     */
    public function getDeviceAttendanceByDateRange(string $deviceId, string $startDate, string $endDate): Collection
    {
        return OfflineAttendance::byDevice($deviceId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date')
            ->get();
    }

    /**
     * Retry failed syncs
     */
    public function retrySyncErrors(int $maxRetries = 3): array
    {
        $failed = OfflineAttendance::whereNotNull('sync_error')
            ->orderBy('updated_at')
            ->limit($maxRetries)
            ->get();

        $synced = 0;
        $stillFailed = 0;

        foreach ($failed as $record) {
            if ($this->syncAttendanceRecord($record)) {
                $synced++;
            } else {
                $stillFailed++;
            }
        }

        return [
            'retried' => $failed->count(),
            'synced' => $synced,
            'still_failed' => $stillFailed,
        ];
    }
}
