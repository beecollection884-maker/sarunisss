<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\ScheduleDisplayService;
use App\Services\ScheduleGeneratorService;
use App\Services\ScheduleOptimizerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk mengelola jadwal pelajaran
 * 
 * Endpoint:
 * - GET    /schedule/generate-page          - Tampilkan form generate jadwal
 * - POST   /schedule/generate                - Generate jadwal
 * - GET    /schedule/class/{id}/{year}       - Tampilkan jadwal kelas
 * - GET    /schedule/teacher/{id}/{year}     - Tampilkan jadwal guru
 * - GET    /schedule/analyze/{year}          - Analisis jadwal
 * - GET    /schedule/export/{classId}/{year}/{format} - Export jadwal
 */
class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleGeneratorService $scheduleGenerator,
        private ScheduleOptimizerService $optimizer,
        private ScheduleDisplayService $displayService
    ) {}

    /**
     * Get data untuk form pembuatan jadwal (API)
     */
    public function getFormData()
    {
        $classes = SchoolClass::query()->orderBy('name')->get();
        $teachers = Teacher::query()
            ->with('subjects:id,name')
            ->orderBy('name')
            ->get();
        $subjects = Subject::query()->orderBy('name')->get();
        $dayOptions = collect(range(0, 5))
            ->map(fn(int $day): array => [
                'value' => $day,
                'label' => config("schedule.day_names.{$day}", 'Hari ' . ($day + 1)),
            ])
            ->all();
        $lessonPeriods = $this->lessonPeriods();

        // Get active academic year from config or recent data
        $activeYear = config('app.default_academic_year', '2025/2026');

        return response()->json([
            'success' => true,
            'data' => [
                'classes' => $classes,
                'teachers' => $teachers,
                'subjects' => $subjects,
                'days' => $dayOptions,
                'periods' => $lessonPeriods,
                'active_year' => $activeYear,
            ]
        ]);
    }

    /**
     * Tampilkan halaman form generate jadwal
     */
    public function generatePage()
    {
        $academicYears = $this->getAvailableAcademicYears();
        $classes = SchoolClass::query()->orderBy('name')->get();
        $teachers = Teacher::query()
            ->with('subjects:id,name')
            ->orderBy('name')
            ->get();
        $subjects = Subject::query()->orderBy('name')->get();
        $dayOptions = collect(range(0, 5))
            ->map(fn(int $day): array => [
                'value' => $day,
                'label' => config("schedule.day_names.{$day}", 'Hari ' . ($day + 1)),
            ])
            ->all();
        $lessonPeriods = $this->lessonPeriods();

        return view('schedule.generate', compact(
            'academicYears',
            'classes',
            'teachers',
            'subjects',
            'dayOptions',
            'lessonPeriods',
        ));
    }

    /**
     * Generate jadwal otomatis
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'], // Format: 2025/2026
            'school_class_id' => 'nullable|exists:school_classes,id',
            'clear_existing' => 'boolean',
            'validate_only' => 'boolean',
        ]);

        try {
            $academicYear = $validated['academic_year'];
            $schoolClassId = $validated['school_class_id'] ?? null;

            // Validasi terlebih dahulu
            $validation = $this->scheduleGenerator->validateBeforeGeneration($academicYear);

            if (!$validation['is_valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // Jika hanya validasi
            if ($validated['validate_only'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Validasi berhasil',
                    'warnings' => $validation['warnings'],
                ]);
            }

            // Clear existing jika diminta
            if ($validated['clear_existing'] ?? false) {
                $this->scheduleGenerator->clearSchedule($academicYear, $schoolClassId);
            }

            // Generate jadwal
            $result = $this->scheduleGenerator->generateSchedule($academicYear, $schoolClassId);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dibuat',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buat jadwal manual secara individual
     */
    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string',
            'school_class_id' => 'required|exists:school_classes,id',
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:50',
        ]);

        // 1. Validasi Konflik Guru
        $teacherConflict = TeachingAssignment::where('teacher_id', $validated['teacher_id'])
            ->where('academic_year', $validated['academic_year'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where(function($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhere(function($q2) use ($validated) {
                      $q2->where('start_time', '<=', $validated['start_time'])
                         ->where('end_time', '>=', $validated['end_time']);
                  });
            })->first();

        if ($teacherConflict) {
            $conflictClass = SchoolClass::find($teacherConflict->school_class_id);
            return response()->json([
                'success' => false,
                'message' => "Konflik Jadwal: Guru sudah memiliki jadwal mengajar di kelas {$conflictClass->name} pada hari dan jam tersebut."
            ], 422);
        }

        // 2. Validasi Konflik Kelas (Kelas sudah ada jadwal di jam tersebut)
        $classConflict = TeachingAssignment::where('school_class_id', $validated['school_class_id'])
            ->where('academic_year', $validated['academic_year'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where(function($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhere(function($q2) use ($validated) {
                      $q2->where('start_time', '<=', $validated['start_time'])
                         ->where('end_time', '>=', $validated['end_time']);
                  });
            })->first();

        if ($classConflict) {
            $conflictSubject = Subject::find($classConflict->subject_id);
            return response()->json([
                'success' => false,
                'message' => "Konflik Jadwal: Kelas sudah memiliki pelajaran {$conflictSubject->name} pada hari dan jam tersebut."
            ], 422);
        }

        // 3. Simpan
        $assignment = TeachingAssignment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil disimpan.',
            'data' => $assignment
        ]);
    }

    /**
     * Tampilkan jadwal kelas
     */
    public function showClassSchedule(int $classId, string $academicYear)
    {
        $class = SchoolClass::findOrFail($classId);
        $schedule = $this->displayService->getClassScheduleTable($classId, $academicYear);
        $teachers = Teacher::orderBy('name')->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $schedule,
            ]);
        }

        return view('schedule.class-schedule', compact('class', 'schedule', 'academicYear', 'teachers'));
    }

    /**
     * Tampilkan jadwal guru
     */
    public function showTeacherSchedule(int $teacherId, string $academicYear)
    {
        $teacher = Teacher::findOrFail($teacherId);
        $schedule = $this->displayService->getTeacherScheduleTable($teacherId, $academicYear);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $schedule,
            ]);
        }

        return view('schedule.teacher-schedule', compact('teacher', 'schedule', 'academicYear'));
    }

    /**
     * Analisis jadwal dan tampilkan rekomendasi
     */
    public function analyze(string $academicYear)
    {
        try {
            $analysis = $this->optimizer->generateScheduleReport($academicYear);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $analysis,
                ]);
            }

            return view('schedule.analyze', compact('analysis', 'academicYear'));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get deteksi konflik guru
     */
    public function teacherConflicts(string $academicYear)
    {
        $conflicts = $this->optimizer->detectTeacherConflicts($academicYear);

        return response()->json([
            'success' => true,
            'total_conflicts' => count($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get deteksi konflik ruangan
     */
    public function roomConflicts(string $academicYear)
    {
        $conflicts = $this->optimizer->detectRoomConflicts($academicYear);

        return response()->json([
            'success' => true,
            'total_conflicts' => count($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get analisis beban kerja guru
     */
    public function teacherWorkload(string $academicYear)
    {
        $workloads = $this->optimizer->analyzeTeacherWorkload($academicYear);

        return response()->json([
            'success' => true,
            'data' => $workloads,
        ]);
    }

    /**
     * Get analisis distribusi jadwal per hari
     */
    public function dailyDistribution(int $classId, string $academicYear)
    {
        $distribution = $this->optimizer->analyzeDailyDistribution($academicYear, $classId);

        return response()->json([
            'success' => true,
            'data' => $distribution,
        ]);
    }

    /**
     * Export jadwal ke berbagai format
     */
    public function export(int $classId, string $academicYear, string $format = 'html')
    {
        $class = SchoolClass::findOrFail($classId);

        $format = strtolower($format);

        if ($format === 'html') {
            $content = $this->displayService->exportToHTML($classId, $academicYear);
            return response($content, 200)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=\"jadwal_{$class->name}_{$academicYear}.html\"");
        } elseif ($format === 'csv') {
            $content = $this->displayService->exportToCSV($classId, $academicYear);
            return response($content, 200)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=\"jadwal_{$class->name}_{$academicYear}.csv\"");
        } elseif ($format === 'ics') {
            $content = $this->displayService->generateICSCalendar($classId, $academicYear);
            return response($content, 200)
                ->header('Content-Type', 'text/calendar; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=\"jadwal_{$class->name}_{$academicYear}.ics\"");
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Format tidak didukung. Pilih: html, csv, ics',
            ], 400);
        }
    }

    /**
     * List semua jadwal untuk tahun akademik
     */
    public function list(string $academicYear)
    {
        $schedules = TeachingAssignment::query()
            ->with(['teacher', 'subject', 'schoolClass'])
            ->where('academic_year', $academicYear)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Get rekomendasi perbaikan jadwal
     */
    public function recommendations(string $academicYear)
    {
        $recommendations = $this->optimizer->getRecommendations($academicYear);

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
        ]);
    }

    // ===== Helper Methods =====

    private function getAvailableAcademicYears(): array
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;

        // Build available years: include existing years from DB + current/next range
        $years = [];

        // Add years that already exist in school_classes table
        $existingYears = SchoolClass::query()
            ->select('academic_year')
            ->distinct()
            ->pluck('academic_year')
            ->toArray();

        foreach ($existingYears as $year) {
            $years[$year] = str_replace('/', ' / ', $year);
        }

        // Add current and next academic year ranges (using slash format)
        $currentRange = "{$currentYear}/{$nextYear}";
        $nextRange = "{$nextYear}/" . ($nextYear + 1);

        if (!isset($years[$currentRange])) {
            $years[$currentRange] = "{$currentYear} / {$nextYear}";
        }
        if (!isset($years[$nextRange])) {
            $years[$nextRange] = "{$nextYear} / " . ($nextYear + 1);
        }

        ksort($years);

        return $years;
    }

    /**
     * @return array<int, array{period:int,start_time:string,end_time:string,label:string}>
     */
    private function lessonPeriods(): array
    {
        $startHour = (int) config('schedule.school_start_hour', 7);
        $endHour = (int) config('schedule.school_end_hour', 15);
        $duration = max(1, (int) config('schedule.lesson_duration', 45));
        $startMinutes = $startHour * 60;
        $endMinutes = $endHour * 60;
        $periods = [];
        $period = 1;

        for ($cursor = $startMinutes; $cursor + $duration <= $endMinutes; $cursor += $duration) {
            $start = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
            $end = sprintf('%02d:%02d', intdiv($cursor + $duration, 60), ($cursor + $duration) % 60);
            $periods[] = [
                'period' => $period,
                'start_time' => $start,
                'end_time' => $end,
                'label' => "Jam ke-{$period} ({$start} - {$end})",
            ];
            $period++;
        }

        return $periods;
    }
}
