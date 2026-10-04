<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminListRequest;
use App\Http\Requests\Admin\UpsertTeachingAssignmentRequest;
use App\Models\TeachingAssignment;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;

class TeachingAssignmentController extends Controller
{
    public function __construct(
        protected TeachingAssignmentService $teachingAssignmentService,
    ) {
    }

    public function index(AdminListRequest $request): JsonResponse
    {
        return response()->json(
            $this->teachingAssignmentService->paginate($request->integer('per_page', 15))
        );
    }

    public function store(UpsertTeachingAssignmentRequest $request): JsonResponse
    {
        $teachingAssignment = $this->teachingAssignmentService->create($request->validated());

        return response()->json([
            'message' => 'Jadwal berhasil disimpan.',
            'data' => $teachingAssignment,
        ], 201);
    }

    public function show(TeachingAssignment $teachingAssignment): JsonResponse
    {
        return response()->json([
            'data' => $teachingAssignment->load(['teacher', 'subject', 'schoolClass', 'subjectAttendances.student']),
        ]);
    }

    public function update(UpsertTeachingAssignmentRequest $request, TeachingAssignment $teachingAssignment): JsonResponse
    {
        $teachingAssignment = $this->teachingAssignmentService->update($teachingAssignment, $request->validated());

        return response()->json([
            'message' => 'Jadwal ajar berhasil diperbarui.',
            'data' => $teachingAssignment,
        ]);
    }

    public function destroy(TeachingAssignment $teachingAssignment): JsonResponse
    {
        $this->teachingAssignmentService->delete($teachingAssignment);

        return response()->json([
            'message' => 'Jadwal ajar berhasil dihapus.',
        ]);
    }
}
