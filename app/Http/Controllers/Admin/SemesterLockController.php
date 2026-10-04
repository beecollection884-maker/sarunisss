<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SemesterLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SemesterLockController extends Controller
{
    public function __construct(
        protected SemesterLockService $semesterLockService,
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        $payload = $this->validatedPeriod($request);
        $lock = $this->semesterLockService->get($payload['academic_year'], $payload['semester']);

        return response()->json([
            'data' => [
                'locked' => $lock !== null,
                'lock' => $lock,
            ],
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        $payload = $this->validatedPeriod($request) + $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $lock = $this->semesterLockService->lock(
            $payload['academic_year'],
            $payload['semester'],
            $request->user(),
            $payload['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Semester berhasil ditutup. Absensi semester ini sekarang readonly.',
            'data' => $lock->load('lockedBy:id,name,email'),
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $payload = $this->validatedPeriod($request);
        $this->semesterLockService->unlock($payload['academic_year'], $payload['semester']);

        return response()->json([
            'message' => 'Lock semester berhasil dibuka.',
        ]);
    }

    protected function validatedPeriod(Request $request): array
    {
        return $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
        ]);
    }
}
