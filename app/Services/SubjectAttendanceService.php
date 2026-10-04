<?php

namespace App\Services;

use App\Models\SubjectAttendance;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectAttendanceService
{
    public function __construct(
        protected AppSettingService $appSettingService,
        protected AcademicCalendarService $academicCalendarService,
        protected SemesterLockService $semesterLockService,
    ) {
    }

    /**
     * @param array{
     *   teaching_assignment_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     * @return Collection<int, SubjectAttendance>
     */
    public function recordForTeacher(Teacher $teacher, array $payload): Collection
    {
        $teachingAssignment = TeachingAssignment::query()
            ->with('schoolClass.students')
            ->findOrFail($payload['teaching_assignment_id']);

        if ($teachingAssignment->teacher_id !== $teacher->id) {
            throw new AuthorizationException('Anda tidak berhak mengisi absensi untuk jadwal ini.');
        }

        $this->validateAssignmentStudents($teachingAssignment, $payload['attendances']);
        $this->ensureAttendanceCanBeRecorded($payload['attendance_date']);
        $this->ensureAssignmentMatchesDate($teachingAssignment, $payload['attendance_date']);
        $this->persistAttendances($teacher->id, $payload);

        return $this->recapForTeacher($teacher, [
            'teaching_assignment_id' => $teachingAssignment->id,
            'attendance_date' => $payload['attendance_date'],
        ]);
    }

    /**
     * @param array{
     *   teacher_id?:int|null,
     *   school_class_id?:int|null,
     *   subject_id?:int|null,
     *   teaching_assignment_id?:int|null,
     *   student_id?:int|null,
     *   attendance_date?:string|null,
     *   date_from?:string|null,
     *   date_to?:string|null
     * } $filters
     * @return Collection<int, SubjectAttendance>
     */
    public function recap(array $filters = []): Collection
    {
        return SubjectAttendance::query()
            ->with([
                'student',
                'recordedByTeacher',
                'teachingAssignment.subject',
                'teachingAssignment.schoolClass',
                'teachingAssignment.teacher',
            ])
            ->whereHas('teachingAssignment', function ($query) use ($filters): void {
                $query
                    ->when(
                        $filters['teacher_id'] ?? null,
                        fn ($teacherQuery, int $teacherId) => $teacherQuery->where('teacher_id', $teacherId),
                    )
                    ->when(
                        $filters['school_class_id'] ?? null,
                        fn ($classQuery, int $schoolClassId) => $classQuery->where('school_class_id', $schoolClassId),
                    )
                    ->when(
                        $filters['subject_id'] ?? null,
                        fn ($subjectQuery, int $subjectId) => $subjectQuery->where('subject_id', $subjectId),
                    )
                    ->when(
                        $filters['teaching_assignment_id'] ?? null,
                        fn ($assignmentQuery, int $assignmentId) => $assignmentQuery->where('id', $assignmentId),
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
     *   subject_id?:int|null,
     *   teaching_assignment_id?:int|null,
     *   student_id?:int|null,
     *   attendance_date?:string|null,
     *   date_from?:string|null,
     *   date_to?:string|null
     * } $filters
     * @return Collection<int, SubjectAttendance>
     */
    public function recapForTeacher(Teacher $teacher, array $filters = []): Collection
    {
        $filters['teacher_id'] = $teacher->id;

        return $this->recap($filters);
    }

    /**
     * @param array{
     *   teaching_assignment_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     * @return Collection<int, SubjectAttendance>
     */
    public function recordForAdmin(array $payload): Collection
    {
        $teachingAssignment = TeachingAssignment::query()
            ->with('schoolClass.students')
            ->findOrFail($payload['teaching_assignment_id']);

        $this->validateAssignmentStudents($teachingAssignment, $payload['attendances']);
        $this->ensureAttendanceCanBeRecorded($payload['attendance_date']);
        $this->ensureAssignmentMatchesDate($teachingAssignment, $payload['attendance_date']);
        $this->persistAttendances($teachingAssignment->teacher_id, $payload);

        return $this->recap([
            'teaching_assignment_id' => $teachingAssignment->id,
            'attendance_date' => $payload['attendance_date'],
        ]);
    }

    /**
     * @param array<int, array{student_id:int,status:string,notes?:string|null}> $attendances
     */
    protected function validateAssignmentStudents(TeachingAssignment $teachingAssignment, array $attendances): void
    {
        $validStudentIds = $teachingAssignment->schoolClass->students->pluck('id')->all();
        $submittedStudentIds = collect($attendances)->pluck('student_id')->all();

        if (count($submittedStudentIds) !== count(array_unique($submittedStudentIds))) {
            throw ValidationException::withMessages([
                'attendances' => ['Data absensi berisi siswa yang sama lebih dari satu kali.'],
            ]);
        }

        $invalidStudentIds = array_values(array_diff($submittedStudentIds, $validStudentIds));

        if ($invalidStudentIds !== []) {
            throw ValidationException::withMessages([
                'attendances' => ['Terdapat siswa yang tidak termasuk dalam kelas jadwal mapel ini.'],
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

    protected function ensureAssignmentMatchesDate(TeachingAssignment $teachingAssignment, string $attendanceDate): void
    {
        if ($this->appSettingService->boolean('attendance_test_mode')) {
            return;
        }

        $date = CarbonImmutable::parse($attendanceDate);
        $scheduleDay = $this->scheduleDayFromIso($date->dayOfWeekIso);

        if ((int) $teachingAssignment->day_of_week !== $scheduleDay) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Tanggal absensi tidak sesuai dengan hari jadwal pelajaran ini.'],
            ]);
        }
    }

    protected function scheduleDayFromIso(int $dayOfWeekIso): int
    {
        return $dayOfWeekIso - 1;
    }

    /**
     * @param array{
     *   teaching_assignment_id:int,
     *   attendance_date:string,
     *   attendances:array<int, array{student_id:int,status:string,notes?:string|null}>
     * } $payload
     */
    protected function persistAttendances(int $recordedByTeacherId, array $payload): void
    {
        DB::transaction(function () use ($recordedByTeacherId, $payload): void {
            foreach ($payload['attendances'] as $attendance) {
                SubjectAttendance::query()->updateOrCreate(
                    [
                        'teaching_assignment_id' => $payload['teaching_assignment_id'],
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
}

