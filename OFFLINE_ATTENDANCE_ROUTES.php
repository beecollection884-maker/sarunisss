<?php

/**
 * Offline Attendance Routes
 * 
 * Routes untuk fitur absensi offline dengan auto-sync
 * 
 * Prefix: /api/attendance/offline
 */

use App\Http\Controllers\OfflineAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'device.auth'])->group(function () {
    // Record attendance offline
    Route::post('/record', [OfflineAttendanceController::class, 'recordAttendance'])
        ->middleware('throttle:60,1')
        ->name('offline.record');

    // Get unsynced records
    Route::get('/unsynced', [OfflineAttendanceController::class, 'getUnsyncedRecords'])
        ->name('offline.unsynced');

    Route::get('/unsynced/device', [OfflineAttendanceController::class, 'getUnsyncedByDevice'])
        ->name('offline.unsynced.device');

    // Sync records
    Route::post('/sync', [OfflineAttendanceController::class, 'syncRecords'])
        ->name('offline.sync')
        ->withoutMiddleware('auth:sanctum'); // Allow auto-sync

    Route::post('/sync/{id}', [OfflineAttendanceController::class, 'syncRecord'])
        ->name('offline.sync.single');

    // Retry failed syncs
    Route::post('/sync/retry', [OfflineAttendanceController::class, 'retrySyncErrors'])
        ->name('offline.sync.retry');

    // Statistics
    Route::get('/statistics', [OfflineAttendanceController::class, 'getStatistics'])
        ->name('offline.stats');

    Route::get('/statistics/device', [OfflineAttendanceController::class, 'getDeviceStatistics'])
        ->name('offline.stats.device');

    // Get attendance data
    Route::get('/student/{studentId}/{date}', [OfflineAttendanceController::class, 'getStudentAttendance'])
        ->name('offline.student.attendance');

    Route::get('/device/range', [OfflineAttendanceController::class, 'getDeviceAttendanceByDateRange'])
        ->name('offline.device.range');

    // Maintenance
    Route::delete('/clear-old', [OfflineAttendanceController::class, 'clearOldRecords'])
        ->name('offline.clear.old');
});

/**
 * Available Endpoints:
 * 
 * POST   /api/attendance/offline/record
 *        Record absensi offline
 *        Body: {offline_device_id, student_id, teacher_id, school_class_id, attendance_type, attendance_date, status, notes}
 * 
 * GET    /api/attendance/offline/unsynced
 *        Get semua unsynced records
 *        Query: ?limit=100
 * 
 * GET    /api/attendance/offline/unsynced/device
 *        Get unsynced records by device
 *        Query: ?device_id=xxx&limit=100
 * 
 * POST   /api/attendance/offline/sync
 *        Sync offline data ke online
 *        Body: {device_id?, limit?}
 * 
 * POST   /api/attendance/offline/sync/{id}
 *        Sync single record
 * 
 * POST   /api/attendance/offline/sync/retry
 *        Retry failed syncs
 *        Body: {max_retries?}
 * 
 * GET    /api/attendance/offline/statistics
 *        Get sync statistics
 * 
 * GET    /api/attendance/offline/statistics/device
 *        Get device statistics
 *        Query: ?device_id=xxx
 * 
 * GET    /api/attendance/offline/student/{studentId}/{date}
 *        Get student attendance by date
 * 
 * GET    /api/attendance/offline/device/range
 *        Get device attendance by date range
 *        Query: ?device_id=xxx&start_date=2026-05-25&end_date=2026-05-26
 * 
 * DELETE /api/attendance/offline/clear-old
 *        Clear old synced records
 *        Body: {days?}
 */
