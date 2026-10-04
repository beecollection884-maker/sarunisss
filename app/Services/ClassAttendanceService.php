<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassAttendanceService
{
    public function __construct(
        protected AppSettingService $appSettingService,
        protected AcademicCalendarService $academicCalendarService,
        protected SemesterLockService $semesterLockService,
    ) {
    }

    /**
     * @param array{
     *   school_class_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     * @return Collection<int, ClassAttendance>
     */
    public function recordForTeacher(Teacher $teacher, array $payload): Collection
    {
        $schoolClass = SchoolClass::query()
            ->with('students')
            ->findOrFail($payload['school_class_id']);

        $isHomeroom = $schoolClass->homeroom_teacher_id === $teacher->id;
        $isFirstPeriod = $this->isFirstPeriodTeacherForDate($teacher, $schoolClass->id, $payload['attendance_date']);

        if (! $isHomeroom && ! $isFirstPeriod) {
            throw new AuthorizationException('Anda tidak berhak mengisi absensi untuk kelas ini.');
        }

        $this->validateClassStudents($schoolClass, $payload['attendances']);
        $this->ensureAttendanceCanBeRecorded($payload['attendance_date']);
        $this->persistAttendances($teacher->id, $payload);

        return $this->recapForTeacher($teacher, [
            'school_class_id' => $schoolClass->id,
            'attendance_date' => $payload['attendance_date'],
        ]);
    }

    /**
     * @param array{
     *   teacher_id?:int|null,
     *   school_class_id?:int|null,
     *   student_id?:int|null,
     *   attendance_date?:string|null,
     *   date_from?:string|null,
     *   date_to?:string|null
     * } $filters
     * @return Collection<int, ClassAttendance>
     */
    public function recap(array $filters = []): Collection
    {
        return ClassAttendance::query()
            ->with(['student', 'schoolClass', 'recordedByTeacher'])
            ->whereHas('schoolClass', function ($query) use ($filters): void {
                $query
                    ->when(
                        $filters['school_class_ids'] ?? null,
                        fn ($classQuery, array $schoolClassIds) => $classQuery->whereIn('id', $schoolClassIds),
                    )
                    ->when(
                        $filters['teacher_id'] ?? null,
                        fn ($teacherQuery, int $teacherId) => $teacherQuery->where('homeroom_teacher_id', $teacherId),
                    )
                    ->when(
                        $filters['school_class_id'] ?? null,
                        fn ($classQuery, int $schoolClassId) => $classQuery->where('id', $schoolClassId),
                    );
            })
            ->when(
                $filters['student_id'] ?? null,
                fn ($query, int $studentId) => $query->where('student_id', $studentId),
            )
            ->when(
                $filters['attendance_date'] ?? null,
                fn ($query, string $attendanceDate) => $query->whereDate('attendance_date', $attendanceDate),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($query, string $dateFrom) => $query->whereDate('attendance_date', '>=', $dateFrom),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($query, string $dateTo) => $query->whereDate('attendance_date', '<=', $dateTo),
            )
            ->orderByDesc('attendance_date')
            ->orderBy('student_id')
            ->get();
    }

    /**
     * @param array{
     *   school_class_id?:int|null,
     *   attendance_date?:string|null,
     *   date_from?:string|null,
     *   date_to?:string|null
     * } $filters
     * @return Collection<int, ClassAttendance>
     */
    public function recapForTeacher(Teacher $teacher, array $filters = []): Collection
    {
        $allowedClassIds = $this->allowedClassIdsForTeacher($teacher);

        if (isset($filters['school_class_id'])) {
            $filters['school_class_id'] = (int) $filters['school_class_id'];

            if (! in_array($filters['school_class_id'], $allowedClassIds, true)) {
                throw new AuthorizationException('Anda tidak berhak mengakses absensi kelas ini.');
            }
        } else {
            $filters['school_class_ids'] = $allowedClassIds;
        }

        return $this->recap($filters);
    }

    /**
     * @param array{
     *   school_class_id?:int|null,
     *   attendance_date?:string|null,
     *   date_from?:string|null,
     *   date_to?:string|null
     * } $filters
     * @return Collection<int, ClassAttendance>
     */
    public function recapForStudent(Student $student, array $filters = []): Collection
    {
        $filters['student_id'] = $student->id;

        return $this->recap($filters);
    }

    /**
     * @param array{
     *   school_class_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     * @return Collection<int, ClassAttendance>
     */
    public function recordForAdmin(array $payload): Collection
    {
        $schoolClass = SchoolClass::query()
            ->with('students')
            ->findOrFail($payload['school_class_id']);

        if ($schoolClass->homeroom_teacher_id === null) {
            throw ValidationException::withMessages([
                'school_class_id' => ['Kelas ini belum memiliki walikelas.'],
            ]);
        }

        $this->validateClassStudents($schoolClass, $payload['attendances']);
        $this->ensureAttendanceCanBeRecorded($payload['attendance_date']);
        $this->persistAttendances($schoolClass->homeroom_teacher_id, $payload);

        return $this->recap([
            'school_class_id' => $schoolClass->id,
            'attendance_date' => $payload['attendance_date'],
        ]);
    }

    /**
     * @param array<int, array{student_id:int,status:string,notes?:string|null}> $attendances
     */
    protected function validateClassStudents(SchoolClass $schoolClass, array $attendances): void
    {
        $validStudentIds = $schoolClass->students->pluck('id')->all();
        $submittedStudentIds = collect($attendances)->pluck('student_id')->all();

        if (count($submittedStudentIds) !== count(array_unique($submittedStudentIds))) {
            throw ValidationException::withMessages([
                'attendances' => ['Data absensi berisi siswa yang sama lebih dari satu kali.'],
            ]);
        }

        $invalidStudentIds = array_values(array_diff($submittedStudentIds, $validStudentIds));

        if ($invalidStudentIds !== []) {
            throw ValidationException::withMessages([
                'attendances' => ['Terdapat siswa yang tidak termasuk dalam kelas ini.'],
            ]);
        }
    }

    protected function ensureAttendanceCanBeRecorded(string $attendanceDate): void
    {
        $academicYear = $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
        $semester = $this->appSettingService->value('active_semester', 'ganjil') ?: 'ganjil';

        if ($this->semesterLockService->isLocked($academicYear, $semester)) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Semester '.$semester.' '.$academicYear.' sudah ditutup. Absensi bersifat readonly.'],
            ]);
        }

        $this->ensureEffectiveSchoolDate($attendanceDate);

        $status = $this->academicCalendarService->attendanceStatusForDate($academicYear, $semester, $attendanceDate);

        if (! $status['allowed']) {
            throw ValidationException::withMessages([
                'attendance_date' => [$status['message']],
            ]);
        }
    }

    protected function ensureEffectiveSchoolDate(string $attendanceDate): void
    {
        $startDateVal = $this->appSettingService->value('school_start_date', '2025-07-14') ?: '2025-07-14';
        $endDateVal = $this->appSettingService->value('school_end_date', '2026-06-30') ?: '2026-06-30';
        $saturdayEnabled = filter_var(
            $this->appSettingService->value('school_saturday_enabled', '1') ?? '1',
            FILTER_VALIDATE_BOOLEAN
        );

        $date = CarbonImmutable::parse($attendanceDate)->startOfDay();
        $start = CarbonImmutable::parse($startDateVal)->startOfDay();
        $end = CarbonImmutable::parse($endDateVal)->startOfDay();

        if ($end->lt($start) || $date->lt($start) || $date->gt($end)) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Tanggal absensi berada di luar rentang KBM aktif.'],
            ]);
        }

        if ($date->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Hari Minggu tidak dihitung sebagai pertemuan KBM.'],
            ]);
        }

        if ($date->dayOfWeekIso === 6 && ! $saturdayEnabled) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Hari Sabtu tidak dihitung sebagai pertemuan KBM pada pengaturan saat ini.'],
            ]);
        }
    }

    /**
     * @param array{
     *   school_class_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     */
    protected function persistAttendances(int $recordedByTeacherId, array $payload): void
    {
        DB::transaction(function () use ($recordedByTeacherId, $payload): void {
            foreach ($payload['attendances'] as $attendance) {
                ClassAttendance::query()->updateOrCreate(
                    [
                        'school_class_id' => $payload['school_class_id'],
                        'student_id' => $attendance['student_id'],
                        'attendance_date' => $payload['attendance_date'],
                    ],
                    [
                        'recorded_by_teacher_id' => $recordedByTeacherId,
                        'status' => $attendance['status'],
                        'notes' => $attendance['notes'] ?? null,
                    ],
                );
            }
        });
    }

    public function isFirstPeriodTeacherForDate(Teacher $teacher, int $schoolClassId, string $dateString): bool
    {
        $schoolClass = SchoolClass::query()->find($schoolClassId);
        $date = CarbonImmutable::parse($dateString);
        $dayOfWeek = $this->scheduleDayFromIso($date->dayOfWeekIso);

        $earliestAssignment = TeachingAssignment::query()
            ->where('school_class_id', $schoolClassId)
            ->when(
                $schoolClass?->academic_year,
                fn ($query, string $academicYear) => $query->where('academic_year', $academicYear),
            )
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time', 'asc')
            ->first();

        return $earliestAssignment !== null && $earliestAssignment->teacher_id === $teacher->id;
    }

    protected function scheduleDayFromIso(int $dayOfWeekIso): int
    {
        return $dayOfWeekIso - 1;
    }

    public function allowedClassIdsForTeacher(Teacher $teacher): array
    {
        $homeroomClassIds = SchoolClass::query()
            ->where('homeroom_teacher_id', $teacher->id)
            ->pluck('id')
            ->all();

        $teacherAssignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->get();

        $firstPeriodClassIds = [];
        foreach ($teacherAssignments as $assignment) {
            $isEarliest = ! TeachingAssignment::query()
                ->where('school_class_id', $assignment->school_class_id)
                ->where('academic_year', $assignment->academic_year)
                ->where('day_of_week', $assignment->day_of_week)
                ->where('start_time', '<', $assignment->start_time)
                ->exists();

            if ($isEarliest) {
                $firstPeriodClassIds[] = $assignment->school_class_id;
            }
        }

        return array_values(array_unique(array_merge($homeroomClassIds, $firstPeriodClassIds)));
    }
}

