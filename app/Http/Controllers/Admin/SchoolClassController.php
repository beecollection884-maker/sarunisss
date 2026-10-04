<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminListRequest;
use App\Http\Requests\Admin\UpsertSchoolClassRequest;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SchoolClassController extends Controller
{
    public function __construct(
        protected SchoolClassService $schoolClassService,
        protected AppSettingService $appSettingService,
    ) {
    }

    public function index(AdminListRequest $request): JsonResponse
    {
        return response()->json(
            $this->schoolClassService->paginate($request->integer('per_page', 15))
        );
    }

    public function store(UpsertSchoolClassRequest $request): JsonResponse
    {
        $schoolClass = $this->schoolClassService->create($request->validated());

        return response()->json([
            'message' => 'Data kelas berhasil dibuat.',
            'data' => $schoolClass,
        ], 201);
    }

    public function show(SchoolClass $schoolClass): JsonResponse
    {
        return response()->json([
            'data' => $schoolClass->load(['homeroomTeacher', 'students', 'teachingAssignments.subject', 'teachingAssignments.teacher']),
        ]);
    }

    public function update(UpsertSchoolClassRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $schoolClass = $this->schoolClassService->update($schoolClass, $request->validated());

        return response()->json([
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => $schoolClass,
        ]);
    }

    public function destroy(SchoolClass $schoolClass): JsonResponse
    {
        $this->schoolClassService->delete($schoolClass);

        return response()->json([
            'message' => 'Data kelas berhasil dihapus.',
        ]);
    }

    public function meetings(SchoolClass $schoolClass): JsonResponse
    {
        $startDateVal = $this->appSettingService->value('school_start_date', '2025-07-14') ?: '2025-07-14';
        $endDateVal = $this->appSettingService->value('school_end_date', '2026-06-30') ?: '2026-06-30';
        $saturdayEnabled = filter_var(
            $this->appSettingService->value('school_saturday_enabled', '1') ?? '1',
            FILTER_VALIDATE_BOOLEAN
        );

        $start = Carbon::parse($startDateVal)->startOfDay();
        $end = Carbon::parse($endDateVal)->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }
        
        $meetings = [];
        $current = $start->copy();
        $meetingNum = 1;

        while ($current->lte($end)) {
            $isSunday = $current->dayOfWeek === Carbon::SUNDAY;
            $isSaturday = $current->dayOfWeek === Carbon::SATURDAY;

            if (!$isSunday && (!$isSaturday || $saturdayEnabled)) {
                $meetings[] = [
                    'number' => $meetingNum,
                    'date' => $current->toDateString(),
                    'formatted_date' => $current->translatedFormat('l, d F Y'),
                    'day_name' => $current->translatedFormat('l'),
                ];
                $meetingNum++;
            }

            $current->addDay();
        }

        return response()->json([
            'data' => $meetings,
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'saturday_enabled' => $saturdayEnabled,
                'total_meetings' => count($meetings),
            ],
        ]);
    }
}


