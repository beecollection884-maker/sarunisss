<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminListRequest;
use App\Http\Requests\Admin\UpsertStudentRequest;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $studentService,
    ) {
    }

    public function index(AdminListRequest $request): JsonResponse
    {
        return response()->json(
            $this->studentService->paginate($request->integer('per_page', 15))
        );
    }

    public function store(UpsertStudentRequest $request): JsonResponse
    {
        $student = $this->studentService->create($request->validated());

        return response()->json([
            'message' => 'Data siswa berhasil dibuat.',
            'data' => $student,
        ], 201);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json([
            'data' => $student->load(['user', 'schoolClass', 'classAttendances', 'subjectAttendances', 'detailSiswa']),
        ]);
    }

    public function update(UpsertStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->studentService->update($student, $request->validated());

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $student,
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->studentService->delete($student);

        return response()->json([
            'message' => 'Data siswa berhasil dihapus.',
        ]);
    }

    public function unassigned(): JsonResponse
    {
        $students = Student::query()->whereNull('school_class_id')->orderBy('name')->get();
        return response()->json([
            'data' => $students
        ]);
    }
}
