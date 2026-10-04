<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateClassPlottingRequest;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use Illuminate\Http\JsonResponse;

class ClassPlottingController extends Controller
{
    public function __construct(
        protected SchoolClassService $schoolClassService,
    ) {
    }

    public function update(UpdateClassPlottingRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $payload = $request->validated();

        $schoolClass = $this->schoolClassService->plotStudentsAndHomeroom(
            $schoolClass,
            $payload['homeroom_teacher_id'] ?? null,
            $payload['student_ids'] ?? [],
            $payload['subject_ids'] ?? [],
        );

        return response()->json([
            'message' => 'Ploting kelas berhasil disimpan.',
            'data' => $schoolClass,
        ]);
    }
}
