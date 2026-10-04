<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminListRequest;
use App\Http\Requests\Admin\UpsertTeacherRequest;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function __construct(
        protected TeacherService $teacherService,
    ) {
    }

    public function index(AdminListRequest $request): JsonResponse
    {
        return response()->json(
            $this->teacherService->paginate($request->integer('per_page', 15), $request->string('search')->toString())
        );
    }

    public function store(UpsertTeacherRequest $request): JsonResponse
    {
        $teacher = $this->teacherService->create($request->validated());

        return response()->json([
            'message' => 'Data guru berhasil dibuat.',
            'data' => $teacher,
        ], 201);
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json([
            'data' => $teacher->load(['user', 'subjects', 'homeroomClasses', 'teachingAssignments.subject', 'teachingAssignments.schoolClass']),
        ]);
    }

    public function update(UpsertTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher = $this->teacherService->update($teacher, $request->validated());

        return response()->json([
            'message' => 'Data guru berhasil diperbarui.',
            'data' => $teacher,
        ]);
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->teacherService->delete($teacher);

        return response()->json([
            'message' => 'Data guru berhasil dihapus.',
        ]);
    }
}
