<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Models\StudentNote;
use App\Models\SubjectAttendance;
use App\Services\ClassAttendanceService;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParentPortalController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected TeachingAssignmentService $teachingAssignmentService,
        protected ClassAttendanceService $classAttendanceService,
    ) {
    }

    public function children(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->parentStudents()
                ->with(['schoolClass', 'detailSiswa'])
                ->orderBy('name')
                ->get(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function schedule(Request $request): JsonResponse
    {
        $student = $this->parentStudentFromRequest($request);

        return response()->json([
            'selected_student' => $student,
            'data' => $this->teachingAssignmentService->scheduleForStudent($student),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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

        $student = $this->parentStudentFromRequest($request);

        return response()->json([
            'selected_student' => $student,
            'data' => $this->classAttendanceService->recapForStudent($student, $filters),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function subjectAttendance(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
            'teaching_assignment_id' => ['nullable', 'integer', Rule::exists('teaching_assignments', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $student = $this->parentStudentFromRequest($request);

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
            'selected_student' => $student,
            'data' => $records,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function notes(Request $request): JsonResponse
    {
        $student = $this->parentStudentFromRequest($request);

        return response()->json([
            'selected_student' => $student,
            'data' => StudentNote::query()
                ->with(['teacher', 'user'])
                ->where('student_id', $student->id)
                ->latest()
                ->get(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
