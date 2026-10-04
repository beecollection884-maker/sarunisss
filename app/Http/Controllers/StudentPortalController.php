<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Models\Student;
use App\Services\ClassAttendanceService;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\SubjectAttendance;
use App\Models\StudentNote;

class StudentPortalController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected TeachingAssignmentService $teachingAssignmentService,
        protected ClassAttendanceService $classAttendanceService,
    ) {
    }

    public function schedule(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            if (($filters['student_id'] ?? null) !== null) {
                $student = Student::query()->findOrFail($filters['student_id']);

                return response()->json([
                    'data' => $this->teachingAssignmentService->scheduleForStudent($student),
                ]);
            }

            return response()->json([
                'data' => $this->teachingAssignmentService->schedules(),
            ]);
        }

        $student = $request->user()?->studentProfile;
        if ($student === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->teachingAssignmentService->scheduleForStudent($student),
        ]);
    }

    public function classAttendance(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->classAttendanceService->recap($filters),
            ]);
        }

        $student = $request->user()?->studentProfile;
        if ($student === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->classAttendanceService->recapForStudent($student, $filters),
        ]);
    }

    public function subjectAttendance(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teaching_assignment_id' => ['nullable', 'integer', Rule::exists('teaching_assignments', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $student = $request->user()?->studentProfile;
        if ($student === null) {
            return response()->json(['data' => []])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $records = SubjectAttendance::query()
            ->with(['teachingAssignment.subject', 'teachingAssignment.teacher', 'teachingAssignment.schoolClass'])
            ->where('student_id', $student->id)
            ->whereHas('teachingAssignment', function ($query) use ($student, $filters): void {
                $query
                    ->where('school_class_id', $student->school_class_id)
                    ->when(
                        $filters['teaching_assignment_id'] ?? null,
                        fn ($assignmentQuery, int $assignmentId) => $assignmentQuery->where('id', $assignmentId),
                    )
                    ->when(
                        $filters['subject_id'] ?? null,
                        fn ($subjectQuery, int $subjectId) => $subjectQuery->where('subject_id', $subjectId),
                    );
            })
            ->when(
                $filters['attendance_date'] ?? null,
                fn ($query, string $attendanceDate) => $query->whereDate('attendance_date', $attendanceDate),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($query, string $dateFrom) => $query->whereDate('attendance_date', '>=', $dateFrom),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($query, string $dateTo) => $query->whereDate('attendance_date', '<=', $dateTo),
            )
            ->orderByDesc('attendance_date')
            ->get();

        return response()->json([
            'data' => $records,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function notes(Request $request): JsonResponse
    {
        $student = $request->user()?->studentProfile;
        if ($student === null) {
            return response()->json(['data' => []])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return response()->json([
            'data' => StudentNote::query()
                ->with(['teacher', 'user'])
                ->where('student_id', $student->id)
                ->latest()
                ->get(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function schedulePage(Request $request): View
    {
        $student = $this->studentFromRequest($request);
        $schedules = $this->teachingAssignmentService->scheduleForStudent($student);

        $dayNames = config('schedule.day_names', []);
        $operationalDays = config('schedule.operational_days', array_keys($dayNames));

        $allScheduleRows = [];
        foreach ($operationalDays as $dayNum) {
            $daySchedules = $schedules->filter(fn($s) => (int)$s->day_of_week === $dayNum)->values();
            if ($daySchedules->isNotEmpty()) {
                $items = $daySchedules->map(fn($assignment, $index) => [
                    'lesson_period' => $index + 1,
                    'time' => substr($assignment->start_time, 0, 5) . ' - ' . substr($assignment->end_time, 0, 5),
                    'subject' => $assignment->subject?->name ?? '-',
                    'teacher' => $assignment->teacher?->name ?? '-',
                    'room' => $assignment->room ?? '-',
                ])->all();

                $allScheduleRows[] = [
                    'day' => $dayNames[$dayNum] ?? 'Hari ' . ($dayNum + 1),
                    'items' => $items,
                ];
            }
        }

        $menuSections = app(\App\Http\Controllers\PortalDashboardController::class)->menuForPortalPage('siswa', 'Jadwal Mata Pelajaran');

        return view('dashboard.student-schedule', [
            'pageTitle' => 'Jadwal Mata Pelajaran',
            'portalKey' => 'siswa',
            'menuSections' => $menuSections,
            'allScheduleRows' => $allScheduleRows,
        ]);
    }

    public function attendancePage(Request $request): View
    {
        $student = $this->studentFromRequest($request);
        
        // Fetch all subject attendances for this student
        $attendances = SubjectAttendance::query()
            ->with(['teachingAssignment.subject', 'teachingAssignment.teacher'])
            ->where('student_id', $student->id)
            ->get();

        // Group by teaching assignment to get per-subject summary
        $recapRows = $attendances->groupBy('teaching_assignment_id')->map(function ($subjectAttendances, $assignmentId) {
            $assignment = $subjectAttendances->first()->teachingAssignment;
            $total = $subjectAttendances->count();
            $alpha = $subjectAttendances->where('status', 'alpha')->count();
            $izin = $subjectAttendances->where('status', 'izin')->count();
            $sakit = $subjectAttendances->where('status', 'sakit')->count();
            $hadir = $subjectAttendances->where('status', 'hadir')->count();
            $presentase = $total > 0 ? round(($hadir / $total) * 100) : 0;

            return [
                'assignment_id' => $assignmentId,
                'subject_name' => $assignment->subject?->name ?? '-',
                'teacher_name' => $assignment->teacher?->name ?? '-',
                'alpha' => $alpha,
                'izin' => $izin,
                'sakit' => $sakit,
                'hadir' => $hadir,
                'total' => $total,
                'presentase' => $presentase,
            ];
        })->values()->all();

        $menuSections = app(\App\Http\Controllers\PortalDashboardController::class)->menuForPortalPage('siswa', 'Daftar Hadir');

        return view('dashboard.student-attendance', [
            'pageTitle' => 'Daftar Hadir',
            'portalKey' => 'siswa',
            'menuSections' => $menuSections,
            'recapRows' => $recapRows,
        ]);
    }

    public function attendanceDetailPage(Request $request, int $assignmentId): View
    {
        $student = $this->studentFromRequest($request);

        $attendances = SubjectAttendance::query()
            ->with(['teachingAssignment.subject', 'teachingAssignment.teacher'])
            ->where('student_id', $student->id)
            ->where('teaching_assignment_id', $assignmentId)
            ->orderByDesc('attendance_date')
            ->get();

        $assignment = $attendances->first()?->teachingAssignment 
            ?? \App\Models\TeachingAssignment::with(['subject', 'teacher'])->findOrFail($assignmentId);

        $menuSections = app(\App\Http\Controllers\PortalDashboardController::class)->menuForPortalPage('siswa', 'Daftar Hadir');

        return view('dashboard.student-attendance-detail', [
            'pageTitle' => 'Detail Daftar Hadir: ' . ($assignment->subject?->name ?? '-'),
            'portalKey' => 'siswa',
            'menuSections' => $menuSections,
            'assignment' => $assignment,
            'attendances' => $attendances,
        ]);
    }
}
