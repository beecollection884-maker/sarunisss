<?php

namespace App\Services;

use App\Models\SemesterLock;
use App\Models\User;

class SemesterLockService
{
    public function lock(string $academicYear, string $semester, ?User $user = null, ?string $notes = null): SemesterLock
    {
        return SemesterLock::query()->updateOrCreate(
            [
                'academic_year' => $academicYear,
                'semester' => $semester,
            ],
            [
                'locked_at' => now(),
                'locked_by' => $user?->id,
                'notes' => $notes,
            ],
        );
    }

    public function unlock(string $academicYear, string $semester): void
    {
        SemesterLock::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->delete();
    }

    public function isLocked(string $academicYear, string $semester): bool
    {
        return SemesterLock::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->exists();
    }

    public function get(string $academicYear, string $semester): ?SemesterLock
    {
        return SemesterLock::query()
            ->with('lockedBy:id,name,email')
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->first();
    }
}
