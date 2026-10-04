<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TeachingAssignmentService
{
    public function __construct(
        protected UserRoleService $userRoleService,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return TeachingAssignment::query()
            ->with(['teacher', 'subject', 'schoolClass' => fn ($query) => $query->withCount('students')])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    public function create(array $data): TeachingAssignment
    {
        $teachingAssignment = TeachingAssignment::create($data)->load(['teacher', 'subject', 'schoolClass']);

        $this->syncTeacherRoleById($teachingAssignment->teacher_id);

        return $teachingAssignment;
    }

    public function update(TeachingAssignment $teachingAssignment, array $data): TeachingAssignment
    {
        $oldTeacherId = $teachingAssignment->teacher_id;
        $teachingAssignment->update($data);

        if ($oldTeacherId !== $teachingAssignment->teacher_id) {
            $this->syncTeacherRoleById($oldTeacherId);
        }

        $this->syncTeacherRoleById($teachingAssignment->teacher_id);

        return $teachingAssignment->load(['teacher', 'subject', 'schoolClass']);
    }

    public function delete(TeachingAssignment $teachingAssignment): void
    {
        $teacherId = $teachingAssignment->teacher_id;
        $teachingAssignment->delete();

        $this->syncTeacherRoleById($teacherId);
    }

    /**
     * @return Collection<int, TeachingAssignment>
     */
    public function schedules(?int $teacherId = null, ?int $schoolClassId = null): Collection
    {
        return TeachingAssignment::query()
            ->with(['teacher', 'subject', 'schoolClass'])
            ->when(
                $teacherId,
                fn ($query, int $resolvedTeacherId) => $query->where('teacher_id', $resolvedTeacherId),
            )
            ->when(
                $schoolClassId,
                fn ($query, int $resolvedSchoolClassId) => $query->where('school_class_id', $resolvedSchoolClassId),
            )
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return Collection<int, TeachingAssignment>
     */
    public function scheduleForTeacher(Teacher $teacher): Collection
    {
        return $this->schedules($teacher->id);
    }

    /**
     * @return Collection<int, Student>
     */
    public function students(?int $teacherId = null, ?int $schoolClassId = null): Collection
    {
        $query = Student::query()
            ->with('schoolClass');

        if ($schoolClassId !== null) {
            return $query
                ->where('school_class_id', $schoolClassId)
                ->orderBy('name')
                ->get();
        }

        if ($teacherId !== null) {
            $classIds = TeachingAssignment::query()
                ->where('teacher_id', $teacherId)
                ->pluck('school_class_id')
                ->unique()
                ->values();

            if ($classIds->isEmpty()) {
                return collect();
            }

            return $query
                ->whereIn('school_class_id', $classIds)
                ->orderBy('name')
                ->get();
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsForTeacher(Teacher $teacher): Collection
    {
        return $this->students($teacher->id);
    }

    /**
     * @return Collection<int, TeachingAssignment>
     */
    public function scheduleForStudent(Student $student): Collection
    {
        if ($student->school_class_id === null) {
            return collect();
        }

        return $this->schedules(null, $student->school_class_id);
    }

    protected function syncTeacherRoleById(?int $teacherId): void
    {
        if ($teacherId === null) {
            return;
        }

        $teacher = Teacher::query()
            ->with('user')
            ->withCount(['subjects', 'teachingAssignments', 'homeroomClasses'])
            ->find($teacherId);

        if ($teacher === null) {
            return;
        }

        $this->userRoleService->syncTeacherRoles($teacher);
    }
}
