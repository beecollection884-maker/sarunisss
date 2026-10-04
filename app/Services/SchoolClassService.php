<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolClassService
{
    public function __construct(
        protected UserRoleService $userRoleService,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return SchoolClass::query()
            ->with(['homeroomTeacher', 'students', 'subjects'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): SchoolClass
    {
        return DB::transaction(function () use ($data): SchoolClass {
            $schoolClass = SchoolClass::create($data);

            $this->syncHomeroomTeacherRole(null, $schoolClass->homeroom_teacher_id);

            return $schoolClass->load(['homeroomTeacher', 'students', 'subjects']);
        });
    }

    public function update(SchoolClass $schoolClass, array $data): SchoolClass
    {
        return DB::transaction(function () use ($schoolClass, $data): SchoolClass {
            $oldHomeroomTeacherId = $schoolClass->homeroom_teacher_id;

            $schoolClass->update($data);

            $this->syncHomeroomTeacherRole($oldHomeroomTeacherId, $schoolClass->homeroom_teacher_id);

            return $schoolClass->load(['homeroomTeacher', 'students', 'subjects']);
        });
    }

    public function delete(SchoolClass $schoolClass): void
    {
        DB::transaction(function () use ($schoolClass): void {
            $oldHomeroomTeacherId = $schoolClass->homeroom_teacher_id;

            $schoolClass->students()->update(['school_class_id' => null]);
            $schoolClass->subjects()->detach();
            $schoolClass->delete();

            $this->syncHomeroomTeacherRole($oldHomeroomTeacherId, null);
        });
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function classes(?int $homeroomTeacherId = null): Collection
    {
        return SchoolClass::query()
            ->with(['students', 'homeroomTeacher', 'subjects'])
            ->when(
                $homeroomTeacherId,
                fn ($query, int $resolvedTeacherId) => $query->where('homeroom_teacher_id', $resolvedTeacherId),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int, int> $studentIds
     * @param array<int, int> $subjectIds
     */
    public function plotStudentsAndHomeroom(
        SchoolClass $schoolClass,
        ?int $homeroomTeacherId,
        array $studentIds,
        array $subjectIds = [],
    ): SchoolClass {
        return DB::transaction(function () use ($schoolClass, $homeroomTeacherId, $studentIds, $subjectIds): SchoolClass {
            $oldHomeroomTeacherId = $schoolClass->homeroom_teacher_id;

            $schoolClass->update([
                'homeroom_teacher_id' => $homeroomTeacherId,
            ]);

            $schoolClass->students()->update(['school_class_id' => null]);

            if ($studentIds !== []) {
                Student::query()
                    ->whereIn('id', $studentIds)
                    ->update(['school_class_id' => $schoolClass->id]);
            }

            $schoolClass->subjects()->sync($subjectIds);
            $this->syncHomeroomTeacherRole($oldHomeroomTeacherId, $homeroomTeacherId);

            return $schoolClass->load(['homeroomTeacher', 'students', 'subjects']);
        });
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsForHomeroomTeacher(Teacher $teacher): Collection
    {
        return $this->students($teacher->id);
    }

    /**
     * @return Collection<int, Student>
     */
    public function students(?int $homeroomTeacherId = null, ?int $schoolClassId = null): Collection
    {
        return Student::query()
            ->with(['schoolClass', 'detailSiswa', 'parentUser'])
            ->when(
                $schoolClassId,
                fn ($query, int $resolvedSchoolClassId) => $query->where('school_class_id', $resolvedSchoolClassId),
            )
            ->when(
                $homeroomTeacherId,
                fn ($query, int $resolvedTeacherId) => $query->whereHas('schoolClass', function ($schoolClassQuery) use ($resolvedTeacherId): void {
                    $schoolClassQuery->where('homeroom_teacher_id', $resolvedTeacherId);
                }),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function classesForHomeroomTeacher(Teacher $teacher): Collection
    {
        return $this->classes($teacher->id);
    }

    /**
     * @param array<int, int> $classIds
     * @return Collection<int, SchoolClass>
     */
    public function classesByIds(array $classIds): Collection
    {
        if ($classIds === []) {
            return collect();
        }

        return SchoolClass::query()
            ->with(['students', 'homeroomTeacher', 'subjects'])
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int, int> $classIds
     * @return Collection<int, Student>
     */
    public function studentsForClassIds(array $classIds, ?int $schoolClassId = null): Collection
    {
        if ($classIds === []) {
            return collect();
        }

        return Student::query()
            ->with(['schoolClass', 'detailSiswa', 'parentUser'])
            ->whereIn('school_class_id', $classIds)
            ->when(
                $schoolClassId,
                fn ($query, int $resolvedSchoolClassId) => $query->where('school_class_id', $resolvedSchoolClassId),
            )
            ->orderBy('name')
            ->get();
    }

    protected function syncHomeroomTeacherRole(?int $oldTeacherId, ?int $newTeacherId): void
    {
        if ($oldTeacherId !== null && $oldTeacherId !== $newTeacherId) {
            $oldTeacher = Teacher::query()->with('user')->find($oldTeacherId);

            if ($oldTeacher !== null) {
                $this->userRoleService->syncTeacherRoles($oldTeacher);
            }
        }

        if ($newTeacherId !== null) {
            $newTeacher = Teacher::query()->with('user')->find($newTeacherId);

            if ($newTeacher !== null) {
                $this->userRoleService->syncTeacherRoles($newTeacher);
            }
        }
    }
}
