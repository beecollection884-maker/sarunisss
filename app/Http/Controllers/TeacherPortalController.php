<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherPortalController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected TeachingAssignmentService $teachingAssignmentService,
    ) {
    }

    public function schedule(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->teachingAssignmentService->schedules(
                    $filters['teacher_id'] ?? null,
                    $filters['school_class_id'] ?? null,
                ),
            ]);
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'data' => $this->teachingAssignmentService->scheduleForTeacher($teacher),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->teachingAssignmentService->students(
                    $filters['teacher_id'] ?? null,
                    $filters['school_class_id'] ?? null,
                ),
            ]);
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'data' => $this->teachingAssignmentService->studentsForTeacher($teacher),
        ]);
    }
}
