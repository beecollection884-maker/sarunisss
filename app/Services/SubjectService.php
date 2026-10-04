<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SubjectService
{
    public function __construct(
        protected UserRoleService $userRoleService,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Subject::query()
            ->withCount('teachingAssignments')
            ->with(['teachers', 'schoolClasses'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Subject
    {
        $teacherIds = $this->extractTeacherIds($data);
        $classIds = $this->extractClassIds($data);

        return DB::transaction(function () use ($data, $teacherIds, $classIds): Subject {
            $subject = Subject::create($data);
            $subject->teachers()->sync($teacherIds);
            $subject->schoolClasses()->sync($classIds);
            $this->syncTeacherRoles($teacherIds);

            return $subject->load(['teachers', 'schoolClasses', 'teachingAssignments']);
        });
    }

    public function update(Subject $subject, array $data): Subject
    {
        $teacherIds = $this->extractTeacherIds($data);
        $classIds = $this->extractClassIds($data);
        $affectedTeacherIds = array_values(array_unique(array_merge(
            $subject->teachers()->pluck('teachers.id')->all(),
            $teacherIds,
        )));

        DB::transaction(function () use ($subject, $data, $teacherIds, $classIds, $affectedTeacherIds): void {
            $subject->update($data);
            $subject->teachers()->sync($teacherIds);
            $subject->schoolClasses()->sync($classIds);
            $this->syncTeacherRoles($affectedTeacherIds);
        });

        return $subject->refresh()->load(['teachers', 'schoolClasses', 'teachingAssignments']);
    }

    public function delete(Subject $subject): void
    {
        $teacherIds = $subject->teachers()->pluck('teachers.id')->all();

        DB::transaction(function () use ($subject, $teacherIds): void {
            $subject->teachers()->detach();
            $subject->schoolClasses()->detach();
            $subject->delete();
            $this->syncTeacherRoles($teacherIds);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, int>
     */
    protected function extractTeacherIds(array &$data): array
    {
        $teacherIds = array_values(array_unique(array_map(
            static fn (mixed $teacherId): int => (int) $teacherId,
            $data['teacher_ids'] ?? [],
        )));

        unset($data['teacher_ids']);

        return array_values(array_filter($teacherIds, static fn (int $teacherId): bool => $teacherId > 0));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, int>
     */
    protected function extractClassIds(array &$data): array
    {
        $classIds = array_values(array_unique(array_map(
            static fn (mixed $classId): int => (int) $classId,
            $data['class_ids'] ?? [],
        )));

        unset($data['class_ids']);

        return array_values(array_filter($classIds, static fn (int $classId): bool => $classId > 0));
    }

    /**
     * @param array<int, int> $teacherIds
     */
    protected function syncTeacherRoles(array $teacherIds): void
    {
        if ($teacherIds === []) {
            return;
        }

        Teacher::query()
            ->with('user')
            ->withCount(['subjects', 'teachingAssignments', 'homeroomClasses'])
            ->whereIn('id', $teacherIds)
            ->get()
            ->each(function (Teacher $teacher): void {
                $this->userRoleService->syncTeacherRoles($teacher);
            });
    }
}
