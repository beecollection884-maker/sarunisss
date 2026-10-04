<?php

namespace App\Http\Controllers;

use App\Models\OfflineAttendance;
use App\Services\OfflineAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller untuk offline attendance API
 * 
 * Endpoints:
 * - POST /api/attendance/offline/record - Record absensi offline
 * - GET /api/attendance/offline/unsynced - Get data belum sync
 * - POST /api/attendance/offline/sync - Sync data
 * - GET /api/attendance/offline/stats - Lihat statistik
 */
class OfflineAttendanceController extends Controller
{
    public function __construct(
        protected OfflineAttendanceService $service
    ) {}

    /**
     * Authorize staff access for operational methods
     */
    protected function authorizeOperational(?Request $request = null): void
    {
        $request = $request ?? request();
        $user = $request->user();
        if ($user !== null) {
            $allowedRoles = ['admin', 'super_admin', 'admin_sekolah', 'guru_mapel', 'guru_piket', 'wakasek_kesiswaan'];
            if (! $user->hasAnyRole($allowedRoles)) {
                abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki akses ke fitur absensi offline.');
            }
        }
    }

    /**
     * Authorize admin access for maintenance methods
     */
    protected function authorizeAdmin(?Request $request = null): void
    {
        $request = $request ?? request();
        $user = $request->user();
        if ($user !== null) {
            $allowedRoles = ['admin', 'super_admin', 'admin_sekolah'];
            if (! $user->hasAnyRole($allowedRoles)) {
                abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki izin untuk mengelola pemeliharaan absensi offline.');
            }
        }
    }

    /**
     * Record attendance in offline mode
     * 
     * @bodyParam offline_device_id string required Device ID
     * @bodyParam student_id int required
     * @bodyParam teacher_id int required
     * @bodyParam school_class_id int required
     * @bodyParam teaching_assignment_id int optional
     * @bodyParam attendance_type string required 'class' or 'subject'
     * @bodyParam attendance_date date required
     * @bodyParam status string required 'hadir','sakit','izin','alfa'
     * @bodyParam notes string optional
     */
    public function recordAttendance(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $validated = $request->validate([
            'offline_device_id' => 'required|string',
            'student_id' => 'required|exists:students,id',
            'teacher_id' => 'required|exists:teachers,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'teaching_assignment_id' => 'nullable|exists:teaching_assignments,id',
            'attendance_type' => 'required|in:class,subject',
            'attendance_date' => 'required|date',
            'status' => 'required|in:hadir,sakit,izin,alfa',
            'notes' => 'nullable|string',
        ]);

        $record = $this->service->recordOfflineAttendance($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Absensi berhasil dicatat (offline)',
            'data' => $record,
        ], 201);
    }

    /**
     * Get unsynced offline attendance records
     * 
     * @queryParam limit int Default 100
     */
    public function getUnsyncedRecords(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $limit = $request->query('limit', 100);
        $records = $this->service->getUnsyncedRecords((int) $limit);

        return response()->json([
            'status' => 'success',
            'count' => $records->count(),
            'data' => $records,
        ]);
    }

    /**
     * Get unsynced records by device
     * 
     * @queryParam device_id string required Device ID
     * @queryParam limit int Default 100
     */
    public function getUnsyncedByDevice(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $validated = $request->validate([
            'device_id' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $limit = $validated['limit'] ?? 100;
        $records = $this->service->getUnsyncedByDevice($validated['device_id'], (int) $limit);

        return response()->json([
            'status' => 'success',
            'device_id' => $validated['device_id'],
            'count' => $records->count(),
            'data' => $records,
        ]);
    }

    /**
     * Sync offline attendance records otomatis
     * Auto-sync ketika device terhubung jaringan
     * 
     * @queryParam device_id string optional Filter by device
     * @queryParam limit int Default 100
     */
    public function syncRecords(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $validated = $request->validate([
            'device_id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $limit = $validated['limit'] ?? 100;

        if ($validated['device_id'] ?? null) {
            $result = $this->service->syncDeviceRecords($validated['device_id'], (int) $limit);
        } else {
            $result = $this->service->syncAllRecords((int) $limit);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sync process completed',
            'result' => $result,
        ]);
    }

    /**
     * Sync single record
     */
    public function syncRecord($id): JsonResponse
    {
        $this->authorizeOperational();

        $offlineRecord = OfflineAttendance::findOrFail($id);

        if ($this->service->syncAttendanceRecord($offlineRecord)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Record synced successfully',
                'data' => $offlineRecord->refresh(),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to sync record',
            'error' => $offlineRecord->sync_error,
        ], 400);
    }

    /**
     * Get overall statistics
     */
    public function getStatistics(): JsonResponse
    {
        $this->authorizeAdmin();

        $stats = $this->service->getStatistics();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get device statistics
     * 
     * @queryParam device_id string required
     */
    public function getDeviceStatistics(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $validated = $request->validate([
            'device_id' => 'required|string',
        ]);

        $stats = $this->service->getDeviceStatistics($validated['device_id']);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get attendance by student and date
     */
    public function getStudentAttendance(int $studentId, string $date): JsonResponse
    {
        $this->authorizeOperational();

        $records = $this->service->getStudentAttendanceByDate($studentId, $date);

        return response()->json([
            'status' => 'success',
            'student_id' => $studentId,
            'date' => $date,
            'count' => $records->count(),
            'data' => $records,
        ]);
    }

    /**
     * Get attendance by device and date range
     */
    public function getDeviceAttendanceByDateRange(Request $request): JsonResponse
    {
        $this->authorizeOperational($request);

        $validated = $request->validate([
            'device_id' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = $this->service->getDeviceAttendanceByDateRange(
            $validated['device_id'],
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'status' => 'success',
            'device_id' => $validated['device_id'],
            'date_range' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ],
            'count' => $records->count(),
            'data' => $records,
        ]);
    }

    /**
     * Retry failed syncs
     */
    public function retrySyncErrors(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'max_retries' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->service->retrySyncErrors($validated['max_retries'] ?? 3);

        return response()->json([
            'status' => 'success',
            'result' => $result,
        ]);
    }

    /**
     * Clear old synced records
     */
    public function clearOldRecords(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $deleted = $this->service->clearOldSyncedRecords($validated['days'] ?? 30);

        return response()->json([
            'status' => 'success',
            'message' => "Deleted $deleted old records",
            'deleted_count' => $deleted,
        ]);
    }
}
