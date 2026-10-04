<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

class UserRoleService
{
    /**
     * @param array<int, UserRole|string> $roles
     */
    public function ensureRoles(?User $user, array $roles): void
    {
        if ($user === null) {
            return;
        }

        $normalizedRoles = $this->normalizeRoles($roles);
        $mergedRoles = array_values(array_unique([
            ...($user->roles ?? []),
            ...$normalizedRoles,
        ]));

        if ($mergedRoles === ($user->roles ?? [])) {
            return;
        }

        $user->forceFill(['roles' => $mergedRoles])->save();
    }

    /**
     * @param array<int, UserRole|string> $roles
     */
    public function removeRoles(?User $user, array $roles): void
    {
        if ($user === null) {
            return;
        }

        $updatedRoles = array_values(array_diff(
            $user->roles ?? [],
            $this->normalizeRoles($roles),
        ));

        if ($updatedRoles === ($user->roles ?? [])) {
            return;
        }

        $user->forceFill(['roles' => $updatedRoles])->save();
    }

    public function syncTeacherRoles(Teacher $teacher): void
    {
        if ($teacher->user === null) {
            return;
        }

        $this->ensureRoles($teacher->user, [UserRole::GURU_MAPEL]);
    }

    public function syncStudentRole(Student $student): void
    {
        $this->ensureRoles($student->user, [UserRole::SISWA]);
    }

    public function detachTeacherRoles(?User $user): void
    {
        $this->removeRoles($user, [UserRole::GURU_MAPEL]);
    }

    public function detachStudentRole(?User $user): void
    {
        $this->removeRoles($user, [UserRole::SISWA]);
    }

    /**
     * @param array<int, UserRole|string> $desiredRoles
     * @param array<int, UserRole|string> $scopeRoles
     */
    public function syncScopedRoles(?User $user, array $desiredRoles, array $scopeRoles): void
    {
        if ($user === null) {
            return;
        }

        $desired = $this->normalizeRoles($desiredRoles);
        $scope = $this->normalizeRoles($scopeRoles);

        $preservedRoles = array_values(array_filter(
            $user->roles ?? [],
            static fn(string $role): bool => ! in_array($role, $scope, true),
        ));

        $updatedRoles = array_values(array_unique([
            ...$preservedRoles,
            ...$desired,
        ]));

        if ($updatedRoles === ($user->roles ?? [])) {
            return;
        }

        $user->forceFill(['roles' => $updatedRoles])->save();
    }

    /**
     * @param array<int, UserRole|string> $roles
     * @return array<int, string>
     */
    protected function normalizeRoles(array $roles): array
    {
        return array_values(array_unique(array_map(
            static fn(UserRole|string $role): string => $role instanceof UserRole ? $role->value : $role,
            $roles,
        )));
    }
}
