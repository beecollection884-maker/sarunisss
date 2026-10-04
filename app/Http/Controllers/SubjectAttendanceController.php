<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Services\SubjectAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectAttendanceController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected SubjectAttendanceService $subjectAttendanceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'teaching_assignment_id' => ['nullable', 'integer', Rule::exists('teaching_assignments', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->subjectAttendanceService->recap($filters),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'data' => $this->subjectAttendanceService->recapForTeacher($teacher, $filters),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', Rule::exists('teaching_assignments', 'id')],
            'attendance_date' => ['required', 'date'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'attendances.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'attendances.*.notes' => ['nullable', 'string'],
        ]);

        if (!$request->user()->hasRole(UserRole::ADMIN)) {
            $assignment = \App\Models\TeachingAssignment::findOrFail($payload['teaching_assignment_id']);
            $date = $payload['attendance_date'];
            
            $alreadyFilled = \App\Models\SubjectAttendance::where('teaching_assignment_id', $assignment->id)
                ->where('attendance_date', $date)
                ->exists();

            if ($alreadyFilled) {
                $now = \Carbon\CarbonImmutable::now();
                $targetDate = \Carbon\CarbonImmutable::parse($date);
                $currentTime = $now->format('H:i:s');
                
                $start = $assignment->start_time;
                $end = $assignment->end_time;

                $isSameDay = $targetDate->isSameDay($now);
                $isDuringLesson = $currentTime >= $start && $currentTime <= $end;

                if (!$isSameDay || !$isDuringLesson) {
                    return response()->json([
                        'message' => 'Absensi yang sudah diisi hanya dapat diupdate selama jam pelajaran berlangsung.',
                    ], 422);
                }
            }
        }

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'message' => 'Absensi mapel berhasil disimpan.',
                'data' => $this->subjectAttendanceService->recordForAdmin($payload),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'message' => 'Absensi mapel berhasil disimpan.',
            'data' => $this->subjectAttendanceService->recordForTeacher($teacher, $payload),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
