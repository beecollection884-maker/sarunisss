<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendarService;
use App\Services\AppSettingService;
use App\Services\SemesterLockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCalendarPortalController extends Controller
{
    public function __construct(
        protected AcademicCalendarService $academicCalendarService,
        protected AppSettingService $appSettingService,
        protected SemesterLockService $semesterLockService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'academic_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $filters['academic_year'] ??= $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
        $filters['semester'] ??= $this->appSettingService->value('active_semester', 'ganjil') ?: 'ganjil';
        $filters['is_active'] = true;

        $events = $this->academicCalendarService->list($filters);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'data' => $events,
            ]);
        }

        // Determine portal key
        $portalKey = 'siswa';
        if ($request->is('guru-mapel/*') || $request->is('guru-mapel')) {
            $portalKey = 'guru-mapel';
        } elseif ($request->is('walikelas/*') || $request->is('walikelas')) {
            $portalKey = 'walikelas';
        } elseif ($request->is('orang-tua/*') || $request->is('orang-tua')) {
            $portalKey = 'orang-tua';
        }

        $menuSections = app(PortalDashboardController::class)->menuForPortalPage($portalKey, 'Kalender Akademik');
        $semesterLock = $this->semesterLockService->get($filters['academic_year'], $filters['semester']);

        // Divide events into lists
        $upcomingEvents = $events->filter(fn($event) => $event->end_date >= now()->toDateString())->sortBy('start_date');
        $holidays = $events->filter(fn($event) => $event->is_holiday)->sortBy('start_date');
        $exams = $events->filter(fn($event) => $event->type === 'ujian' || str_contains(strtolower($event->title), 'uts') || str_contains(strtolower($event->title), 'uas'))->sortBy('start_date');

        return view('dashboard.academic-calendar', [
            'pageTitle' => 'Kalender Akademik',
            'portalKey' => $portalKey,
            'menuSections' => $menuSections,
            'activeAcademicYear' => $filters['academic_year'],
            'activeSemester' => $filters['semester'],
            'events' => $events,
            'upcomingEvents' => $upcomingEvents,
            'holidays' => $holidays,
            'exams' => $exams,
            'semesterLock' => $semesterLock,
        ]);
    }

    public function attendanceStatus(Request $request)
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'academic_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);
        $academicYear = $payload['academic_year'] ?? $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
        $semester = $payload['semester'] ?? $this->appSettingService->value('active_semester', 'ganjil') ?: 'ganjil';

        if ($this->semesterLockService->isLocked($academicYear, $semester)) {
            return response()->json([
                'data' => [
                    'allowed' => false,
                    'locked' => true,
                    'message' => 'Semester '.$semester.' '.$academicYear.' sudah ditutup. Absensi bersifat readonly.',
                    'events' => [],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                ...$this->academicCalendarService->attendanceStatusForDate($academicYear, $semester, $payload['date']),
                'locked' => false,
            ],
        ]);
    }
}
