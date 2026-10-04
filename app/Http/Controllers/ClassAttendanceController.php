<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Services\ClassAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassAttendanceController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected ClassAttendanceService $classAttendanceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->classAttendanceService->recap($filters),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'data' => $this->classAttendanceService->recapForTeacher($teacher, $filters),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')],
            'attendance_date' => ['required', 'date'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'attendances.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'attendances.*.notes' => ['nullable', 'string'],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'message' => 'Absensi kelas berhasil disimpan.',
                'data' => $this->classAttendanceService->recordForAdmin($payload),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'message' => 'Absensi kelas berhasil disimpan.',
            'data' => $this->classAttendanceService->recordForTeacher($teacher, $payload),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
