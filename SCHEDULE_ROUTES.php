<?php

/**
 * SCHEDULE ROUTES
 * 
 * Tambahkan route berikut ke routes/web.php atau routes/api.php
 * Sesuaikan middleware dan guard sesuai kebutuhan aplikasi Anda
 * 
 * Untuk menambahkan, buka routes/web.php atau routes/api.php dan paste blok route ini
 */

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// ===== WEB ROUTES =====
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('schedule')->name('schedule.')->group(function () {
        // Generate Schedule
        Route::get('/generate', [ScheduleController::class, 'generatePage'])->name('generate.page');
        Route::post('/generate', [ScheduleController::class, 'generate'])->name('generate');

        // View Schedule
        Route::get('/class/{classId}/{academicYear}', [ScheduleController::class, 'showClassSchedule'])->name('class');
        Route::get('/teacher/{teacherId}/{academicYear}', [ScheduleController::class, 'showTeacherSchedule'])->name('teacher');
        Route::get('/list/{academicYear}', [ScheduleController::class, 'list'])->name('list');

        // Analysis
        Route::get('/analyze/{academicYear}', [ScheduleController::class, 'analyze'])->name('analyze');
        Route::get('/recommendations/{academicYear}', [ScheduleController::class, 'recommendations'])->name('recommendations');

        // Conflicts
        Route::get('/conflicts/teacher/{academicYear}', [ScheduleController::class, 'teacherConflicts'])->name('conflicts.teacher');
        Route::get('/conflicts/room/{academicYear}', [ScheduleController::class, 'roomConflicts'])->name('conflicts.room');

        // Workload
        Route::get('/workload/{academicYear}', [ScheduleController::class, 'teacherWorkload'])->name('workload');
        Route::get('/distribution/{classId}/{academicYear}', [ScheduleController::class, 'dailyDistribution'])->name('distribution');

        // Export
        Route::get('/export/{classId}/{academicYear}/{format}', [ScheduleController::class, 'export'])->name('export');
    });
});

// ===== API ROUTES (Optional) =====
Route::middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('api/schedule')->name('api.schedule.')->group(function () {
        Route::post('/generate', [ScheduleController::class, 'generate'])->name('generate');
        Route::get('/class/{classId}/{academicYear}', [ScheduleController::class, 'showClassSchedule'])->name('class');
        Route::get('/teacher/{teacherId}/{academicYear}', [ScheduleController::class, 'showTeacherSchedule'])->name('teacher');
        Route::get('/analyze/{academicYear}', [ScheduleController::class, 'analyze'])->name('analyze');
        Route::get('/conflicts/teacher/{academicYear}', [ScheduleController::class, 'teacherConflicts'])->name('conflicts.teacher');
        Route::get('/conflicts/room/{academicYear}', [ScheduleController::class, 'roomConflicts'])->name('conflicts.room');
        Route::get('/workload/{academicYear}', [ScheduleController::class, 'teacherWorkload'])->name('workload');
    });
});

/**
 * ENDPOINT REFERENCE
 * 
 * Web Routes:
 * =========
 * 
 * Generate Page:
 *   GET  /schedule/generate                                    - Tampilkan form
 *   POST /schedule/generate                                    - Generate jadwal
 * 
 * View Schedule:
 *   GET  /schedule/class/{classId}/{academicYear}             - Lihat jadwal kelas
 *   GET  /schedule/teacher/{teacherId}/{academicYear}         - Lihat jadwal guru
 *   GET  /schedule/list/{academicYear}                        - List semua jadwal
 * 
 * Analysis:
 *   GET  /schedule/analyze/{academicYear}                     - Analisis lengkap
 *   GET  /schedule/recommendations/{academicYear}             - Rekomendasi perbaikan
 * 
 * Conflict Detection:
 *   GET  /schedule/conflicts/teacher/{academicYear}           - Konflik guru
 *   GET  /schedule/conflicts/room/{academicYear}              - Konflik ruangan
 * 
 * Workload:
 *   GET  /schedule/workload/{academicYear}                    - Analisis beban kerja
 *   GET  /schedule/distribution/{classId}/{academicYear}      - Distribusi harian
 * 
 * Export:
 *   GET  /schedule/export/{classId}/{academicYear}/html       - Export HTML
 *   GET  /schedule/export/{classId}/{academicYear}/csv        - Export CSV
 *   GET  /schedule/export/{classId}/{academicYear}/ics        - Export ICS (Kalender)
 * 
 * 
 * API Routes (JSON):
 * ==================
 * 
 * POST /api/schedule/generate
 * Body: {
 *   "academic_year": "2025-2026",
 *   "school_class_id": null,
 *   "clear_existing": true,
 *   "validate_only": false
 * }
 * 
 * GET /api/schedule/class/{classId}/{academicYear}
 * GET /api/schedule/teacher/{teacherId}/{academicYear}
 * GET /api/schedule/analyze/{academicYear}
 * GET /api/schedule/conflicts/teacher/{academicYear}
 * GET /api/schedule/conflicts/room/{academicYear}
 * GET /api/schedule/workload/{academicYear}
 * 
 * 
 * CONTOH PENGGUNAAN:
 * =================
 * 
 * 1. Generate Jadwal (dari form):
 *    POST /schedule/generate
 *    Form data: academic_year=2025-2026, school_class_id=1, clear_existing=1
 * 
 * 2. Lihat Jadwal Kelas 10A:
 *    GET /schedule/class/1/2025-2026
 * 
 * 3. Export Jadwal ke CSV:
 *    GET /schedule/export/1/2025-2026/csv
 * 
 * 4. Cek Konflik Jadwal:
 *    GET /api/schedule/conflicts/teacher/2025-2026 (JSON response)
 * 
 * 5. Analisis Beban Kerja Guru:
 *    GET /api/schedule/workload/2025-2026 (JSON response)
 */
