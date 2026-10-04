<?php

use App\Http\Controllers\OfflineAttendanceController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes - Offline Attendance
 * 
 * Base URL: /api/attendance/offline
 */

Route::prefix('attendance/offline')->middleware('device.auth')->group(function () {
    // Record attendance offline
    Route::post('/record', [OfflineAttendanceController::class, 'recordAttendance'])
        ->middleware('throttle:60,1')
        ->name('offline.record');

    // Get unsynced records
    Route::get('/unsynced', [OfflineAttendanceController::class, 'getUnsyncedRecords'])
        ->name('offline.unsynced');

    Route::get('/unsynced/device', [OfflineAttendanceController::class, 'getUnsyncedByDevice'])
        ->name('offline.unsynced.device');

    // Sync records (auto-sync on network connection)
    Route::post('/sync', [OfflineAttendanceController::class, 'syncRecords'])
        ->middleware('throttle:60,1')
        ->name('offline.sync');

    Route::post('/sync/{id}', [OfflineAttendanceController::class, 'syncRecord'])
        ->middleware('throttle:60,1')
        ->name('offline.sync.single');

    // Retry failed syncs
    Route::post('/sync/retry', [OfflineAttendanceController::class, 'retrySyncErrors'])
        ->middleware('throttle:60,1')
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

    // Maintenance (delete old records)
    Route::delete('/clear-old', [OfflineAttendanceController::class, 'clearOldRecords'])
        ->middleware('throttle:60,1')
        ->name('offline.clear.old');
});
use App\Http\Controllers\AcademicCalendarPortalController;
use App\Http\Controllers\Admin\AcademicCalendarController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\ClassPlottingController;
use App\Http\Controllers\Admin\DataResetController;
use App\Http\Controllers\Admin\ImportExportController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SemesterLockController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentNoteController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeachingAssignmentController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthRecoveryController;
use App\Http\Controllers\ClassAttendanceController;
use App\Http\Controllers\HomeroomPortalController;
use App\Http\Controllers\ParentPortalController;
use App\Http\Controllers\PortalDashboardController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentViolationController;
use App\Http\Controllers\SubjectAttendanceController;
use App\Http\Controllers\TeacherPortalController;

use App\Http\Controllers\ProfileController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/login/{portal}', [AuthController::class, 'portalLogin'])->middleware('throttle:login');

    // Alur lupa kata sandi (publik): kirim kode -> verifikasi kode -> reset kata sandi.
    Route::post('/auth/forgot-password', [AuthRecoveryController::class, 'sendCode'])->middleware('throttle:auth-recovery');
    Route::post('/auth/verify-code', [AuthRecoveryController::class, 'verifyCode'])->middleware('throttle:auth-recovery');
    Route::post('/auth/reset-password', [AuthRecoveryController::class, 'resetPassword'])->middleware('throttle:auth-recovery');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::get('/attendance-test-mode', function () {
            $settings = app(\App\Services\AppSettingService::class);
            $settings->ensureDefaults();

            return ['enabled' => filter_var($settings->value('attendance_test_mode', '0'), FILTER_VALIDATE_BOOLEAN)];
        });
        Route::get('kelas/{schoolClass}/pertemuan', [SchoolClassController::class, 'meetings']);
    });

    Route::get('/debug-stateful', function () {
        return [
            'is_frontend' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::fromFrontend(request()),
            'host' => request()->getHost(),
            'port' => request()->getPort(),
            'stateful_domains' => config('sanctum.stateful'),
            'referer' => request()->header('referer'),
            'origin' => request()->header('origin'),
        ];
    });    Route::prefix('admin')->middleware(['auth:sanctum', 'role.all:super_admin,admin_sekolah'])->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'admin']);
        Route::get('/dashboard/stats', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
        Route::get('/absensi/rekap', [PortalDashboardController::class, 'adminAttendanceRecap']);
        Route::get('/absensi/laporan', [PortalDashboardController::class, 'adminAttendanceReport']);
        Route::get('/rekap-absensi-kelas', [ClassAttendanceController::class, 'index']);
        Route::get('/rekap-absensi-mapel', [SubjectAttendanceController::class, 'index']);
        Route::get('/data-siswa', [PortalDashboardController::class, 'adminStudents']);
        Route::get('/data-guru', [PortalDashboardController::class, 'adminTeachers']);
        Route::get('/data-kelas', [PortalDashboardController::class, 'adminClasses']);

        Route::post('/import/{type}', [ImportExportController::class, 'import']);
        Route::get('/import/template/{type}', [ImportExportController::class, 'template']);
        Route::get('/export/{dataset}/{format?}', [ImportExportController::class, 'export'])
            ->whereIn('dataset', ['absensi', 'guru', 'siswa', 'kelas', 'mapel', 'catatan-siswa'])
            ->whereIn('format', ['csv', 'xls', 'pdf']);
        Route::resource('pelanggaran', StudentViolationController::class)->except(['create', 'edit', 'show']);

        Route::get('siswa/tidak-ada-kelas', [StudentController::class, 'unassigned']);
        Route::apiResource('siswa', StudentController::class)->parameters(['siswa' => 'student']);
        Route::apiResource('guru', TeacherController::class)->parameters(['guru' => 'teacher']);
        Route::apiResource('kelas', SchoolClassController::class)->parameters(['kelas' => 'schoolClass']);
        Route::apiResource('mapel', SubjectController::class)->parameters(['mapel' => 'subject']);
        Route::apiResource('jadwal-ajar', TeachingAssignmentController::class)->parameters(['jadwal-ajar' => 'teachingAssignment']);
        Route::apiResource('kalender-akademik-data', AcademicCalendarController::class)->parameters(['kalender-akademik-data' => 'kalenderAkademik'])->except(['create', 'edit']);
        Route::get('/semester-lock', [SemesterLockController::class, 'status']);
        Route::post('/semester-lock', [SemesterLockController::class, 'lock']);
        Route::delete('/semester-lock', [SemesterLockController::class, 'unlock']);
        Route::post('pengguna/migrasi-profil', [UserManagementController::class, 'migrateProfiles']);
        Route::apiResource('pengguna', UserManagementController::class)->parameters(['pengguna' => 'pengguna']);
        Route::patch('setting/{setting}/value', [AppSettingController::class, 'updateValue']);
        Route::apiResource('setting', AppSettingController::class)->parameters(['setting' => 'setting']);
        Route::apiResource('catatan', StudentNoteController::class)->parameters(['catatan' => 'catatanSiswa']);
        Route::put('kelas/{schoolClass}/ploting', [ClassPlottingController::class, 'update']);
        Route::get('/pengumuman', [AnnouncementController::class, 'page']);
        Route::apiResource('announcements', AnnouncementController::class)->parameters(['announcements' => 'announcement'])->except(['create', 'edit']);

        Route::get('/data-reset', [DataResetController::class, 'index']);
        Route::post('/data-reset', [DataResetController::class, 'execute']);

        Route::prefix('schedule')->group(function () {
            Route::get('/form-data', [ScheduleController::class, 'getFormData']);
            Route::post('/assignments', [ScheduleController::class, 'storeAssignment']);
            Route::post('/generate', [ScheduleController::class, 'generate']);
            Route::get('/class/{classId}/{academicYear}', [ScheduleController::class, 'showClassSchedule'])->where('academicYear', '.*');
            Route::get('/teacher/{teacherId}/{academicYear}', [ScheduleController::class, 'showTeacherSchedule'])->where('academicYear', '.*');
            Route::get('/analyze/{academicYear}', [ScheduleController::class, 'analyze'])->where('academicYear', '.*');
            Route::get('/conflicts/teacher/{academicYear}', [ScheduleController::class, 'teacherConflicts'])->where('academicYear', '.*');
            Route::get('/conflicts/room/{academicYear}', [ScheduleController::class, 'roomConflicts'])->where('academicYear', '.*');
            Route::get('/workload/{academicYear}', [ScheduleController::class, 'teacherWorkload'])->where('academicYear', '.*');
            Route::get('/distribution/{classId}/{academicYear}', [ScheduleController::class, 'dailyDistribution'])->where('academicYear', '.*');
        });
    });

    Route::prefix('guru-mapel')->middleware('role:guru_mapel')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'teacher']);
        Route::get('/absensi/export/{format}', [PortalDashboardController::class, 'teacherAttendanceExport']);
        Route::get('/jadwal-ajar', [TeacherPortalController::class, 'schedule']);
        Route::get('/siswa', [TeacherPortalController::class, 'students']);
        Route::get('/rekap-absensi-mapel', [SubjectAttendanceController::class, 'index']);
        Route::get('/kalender-akademik', [AcademicCalendarPortalController::class, 'index']);
        Route::get('/status-absensi', [AcademicCalendarPortalController::class, 'attendanceStatus']);
        Route::post('/absensi-mapel', [SubjectAttendanceController::class, 'store']);
    });

    Route::prefix('walikelas')->middleware('homeroom-class')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'homeroom']);
        Route::get('/absensi/export/{format}', [PortalDashboardController::class, 'homeroomAttendanceExport']);
        Route::get('/kelas', [HomeroomPortalController::class, 'classes']);
        Route::get('/siswa', [HomeroomPortalController::class, 'students']);
        Route::get('/catatan', [StudentNoteController::class, 'homeroomIndex']);
        Route::post('/catatan', [StudentNoteController::class, 'homeroomStore']);
        Route::get('/catatan/{catatanSiswa}', [StudentNoteController::class, 'homeroomShow']);
        Route::put('/catatan/{catatanSiswa}', [StudentNoteController::class, 'homeroomUpdate']);
        Route::patch('/catatan/{catatanSiswa}', [StudentNoteController::class, 'homeroomUpdate']);
        Route::delete('/catatan/{catatanSiswa}', [StudentNoteController::class, 'homeroomDestroy']);
        Route::get('/rekap-absensi-kelas', [ClassAttendanceController::class, 'index']);
        Route::get('/kalender-akademik', [AcademicCalendarPortalController::class, 'index']);
        Route::get('/status-absensi', [AcademicCalendarPortalController::class, 'attendanceStatus']);
        Route::post('/absensi-kelas', [ClassAttendanceController::class, 'store']);
    });

    Route::prefix('orang-tua')->middleware('role:orang_tua')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'parent']);
        Route::get('/anak', [ParentPortalController::class, 'children']);
        Route::get('/jadwal-sekolah', [ParentPortalController::class, 'schedule']);
        Route::get('/absensi-mapel', [ParentPortalController::class, 'subjectAttendance']);
        Route::get('/absensi-kelas', [ParentPortalController::class, 'classAttendance']);
        Route::get('/catatan', [ParentPortalController::class, 'notes']);
        Route::get('/kalender-akademik', [AcademicCalendarPortalController::class, 'index']);
    });

    Route::prefix('siswa')->middleware('role:siswa')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'student']);
        Route::get('/jadwal-sekolah', [StudentPortalController::class, 'schedule']);
        Route::get('/absensi-mapel', [StudentPortalController::class, 'subjectAttendance']);
        Route::get('/absensi-kelas', [StudentPortalController::class, 'classAttendance']);
        Route::get('/catatan', [StudentPortalController::class, 'notes']);
        Route::get('/kalender-akademik', [AcademicCalendarPortalController::class, 'index']);
    });

    Route::prefix('wakasek-kesiswaan')->middleware('role:wakasek_kesiswaan')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'wakasekKesiswaan']);
        Route::resource('pelanggaran', StudentViolationController::class)->except(['create', 'edit', 'show']);
    });

    Route::prefix('guru-piket')->middleware('role:guru_piket')->group(function () {
        Route::get('/dashboard', [PortalDashboardController::class, 'guruPiket']);
        Route::resource('pelanggaran', StudentViolationController::class)->except(['create', 'edit', 'show']);
    });
});
