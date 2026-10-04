<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Services\ClassAttendanceService;
use App\Services\SchoolClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeroomPortalController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected SchoolClassService $schoolClassService,
        protected ClassAttendanceService $classAttendanceService,
    ) {
    }

    public function classes(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ]);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'data' => $this->schoolClassService->classes($filters['teacher_id'] ?? null),
            ]);
        }

        $teacher = $this->teacherFromRequest($request);

        return response()->json([
            'data' => $this->schoolClassService->classesByIds(
                $this->classAttendanceService->allowedClassIdsForTeacher($teacher),
            ),
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
                'data' => $this->schoolClassService->students(
                    $filters['teacher_id'] ?? null,
                    $filters['school_class_id'] ?? null,
                ),
            ]);
        }

        $teacher = $this->teacherFromRequest($request);
        $schoolClassId = array_key_exists('school_class_id', $filters) && $filters['school_class_id'] !== null
            ? (int) $filters['school_class_id']
            : null;

        $allowedClassIds = $this->classAttendanceService->allowedClassIdsForTeacher($teacher);

        if ($schoolClassId !== null) {
            abort_unless(in_array($schoolClassId, $allowedClassIds, true), 403, 'Anda tidak berhak mengakses siswa kelas ini.');
        }

        return response()->json([
            'data' => $this->schoolClassService->studentsForClassIds($allowedClassIds, $schoolClassId),
        ]);
    }
}

