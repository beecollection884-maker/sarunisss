<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminListRequest;
use App\Http\Requests\Admin\UpsertSubjectRequest;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function __construct(
        protected SubjectService $subjectService,
    ) {
    }

    public function index(AdminListRequest $request): JsonResponse
    {
        return response()->json(
            $this->subjectService->paginate($request->integer('per_page', 15))
        );
    }

    public function store(UpsertSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->create($request->validated());

        return response()->json([
            'message' => 'Data mapel berhasil dibuat.',
            'data' => $subject,
        ], 201);
    }

    public function show(Subject $subject): JsonResponse
    {
        return response()->json([
            'data' => $subject->load(['teachers', 'schoolClass', 'schoolClasses', 'teachingAssignments.teacher', 'teachingAssignments.schoolClass']),
        ]);
    }

    public function update(UpsertSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjectService->update($subject, $request->validated());

        return response()->json([
            'message' => 'Data mapel berhasil diperbarui.',
            'data' => $subject,
        ]);
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->subjectService->delete($subject);

        return response()->json([
            'message' => 'Data mapel berhasil dihapus.',
        ]);
    }
}
