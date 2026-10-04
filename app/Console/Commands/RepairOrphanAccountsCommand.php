<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RepairOrphanAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sarunis:repair-orphan-accounts {--dry-run : Preview changes without writing to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repairs unlinked teachers, students, and parents, and cleans up duplicate/orphan user accounts.';

    public function handle(UserRoleService $userRoleService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN MODE ENABLED: No changes will be saved to the database.');
        }

        $repairedTeachers = 0;
        $repairedStudents = 0;
        $repairedParents = 0;
        $deletedOrphanUsers = 0;
        $repairedMismatchedRoles = 0;

        // 0. Repair accounts whose profile is still linked but roles are empty (caused by password-change bug)
        $this->info('Checking accounts with linked profile but missing roles...');

        $teachersWithEmptyRoles = Teacher::query()
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        foreach ($teachersWithEmptyRoles as $teacher) {
            $user = $teacher->user;
            if ($user === null) {
                continue;
            }
            $hasTeacherRole = in_array('guru_mapel', $user->roles ?? [], true)
                || in_array('guru_piket', $user->roles ?? [], true);
            if (! $hasTeacherRole) {
                if (! $dryRun) {
                    $userRoleService->syncTeacherRoles($teacher);
                    // Fallback: if teacher has no subjects/homeroom yet, still give guru_mapel
                    $user->refresh();
                    if (empty($user->roles)) {
                        $userRoleService->ensureRoles($user, ['guru_mapel']);
                    }
                }
                $repairedMismatchedRoles++;
                $this->line(" -> [MismatchedRole] Restored roles for teacher '{$teacher->name}' -> User #{$user->id} ({$user->email})");
            }
        }

        $studentsWithEmptyRoles = Student::query()
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        foreach ($studentsWithEmptyRoles as $student) {
            $user = $student->user;
            if ($user === null) {
                continue;
            }
            if (! in_array('siswa', $user->roles ?? [], true)) {
                if (! $dryRun) {
                    $userRoleService->syncStudentRole($student);
                }
                $repairedMismatchedRoles++;
                $this->line(" -> [MismatchedRole] Restored roles for student '{$student->name}' -> User #{$user->id} ({$user->email})");
            }
        }

        // 1. Repair Teachers (teachers.user_id IS NULL)
        $unlinkedTeachers = Teacher::query()->whereNull('user_id')->get();
        $this->info("Checking {$unlinkedTeachers->count()} unlinked teacher profiles...");

        foreach ($unlinkedTeachers as $teacher) {
            $user = User::query()
                ->whereDoesntHave('teacherProfile')
                ->where(function ($query) use ($teacher): void {
                    if (filled($teacher->nip)) {
                        $nipClean = Str::of($teacher->nip)->replaceMatches('/[^0-9a-zA-Z]+/', '')->toString();
                        $query->orWhere('email', 'like', 'guru.' . $teacher->nip . '@%')
                            ->orWhere('email', 'like', 'guru.' . $nipClean . '@%')
                            ->orWhere('email', 'like', 'guru.' . $teacher->nip . '.%@%');
                    }
                    if (filled($teacher->nik)) {
                        $query->orWhere('email', 'like', 'guru.' . $teacher->nik . '@%');
                    }
                    $query->orWhere('name', $teacher->name);
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($user !== null) {
                if (! $dryRun) {
                    $teacher->forceFill(['user_id' => $user->id])->save();
                    $userRoleService->ensureRoles($user, [UserRole::GURU_MAPEL]);
                }
                $repairedTeachers++;
                $this->line(" -> [Teacher] Linked '{$teacher->name}' (Teacher #{$teacher->id}) -> User #{$user->id} ({$user->email})");
            }
        }

        // 2. Repair Students (students.user_id IS NULL)
        $unlinkedStudents = Student::query()->whereNull('user_id')->get();
        $this->info("Checking {$unlinkedStudents->count()} unlinked student profiles...");

        foreach ($unlinkedStudents as $student) {
            $user = User::query()
                ->whereDoesntHave('studentProfile')
                ->where(function ($query) use ($student): void {
                    if (filled($student->nisn)) {
                        $query->orWhere('email', 'like', 'siswa.' . $student->nisn . '@%')
                            ->orWhere('email', 'like', 'siswa.' . $student->nisn . '.%@%');
                    }
                    if (filled($student->nik)) {
                        $query->orWhere('email', 'like', 'siswa.' . $student->nik . '@%');
                    }
                    $query->orWhere('name', $student->name);
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($user !== null) {
                if (! $dryRun) {
                    $student->forceFill(['user_id' => $user->id])->save();
                    $userRoleService->ensureRoles($user, [UserRole::SISWA]);
                }
                $repairedStudents++;
                $this->line(" -> [Student] Linked '{$student->name}' (Student #{$student->id}) -> User #{$user->id} ({$user->email})");
            }
        }

        // 3. Repair Parents (students.parent_user_id IS NULL)
        $unlinkedParents = Student::query()->whereNull('parent_user_id')->with('detailSiswa')->get();
        $this->info("Checking {$unlinkedParents->count()} students with missing parent link...");

        foreach ($unlinkedParents as $student) {
            $fatherName = trim((string) ($student->detailSiswa?->father_name ?? ''));
            $motherName = trim((string) ($student->detailSiswa?->mother_name ?? ''));
            $parentPhone = trim((string) ($student->detailSiswa?->parent_phone ?? ''));

            $parentUser = User::query()
                ->whereJsonContains('roles', UserRole::ORANG_TUA->value)
                ->where(function ($query) use ($fatherName, $motherName, $parentPhone): void {
                    if ($parentPhone !== '') {
                        $phoneSlug = Str::of($parentPhone)->replaceMatches('/[^0-9]+/', '')->toString();
                        if (filled($phoneSlug)) {
                            $query->orWhere('email', 'like', '%.' . $phoneSlug . '@%');
                        }
                    }
                    if ($fatherName !== '') {
                        $query->orWhere('name', $fatherName);
                    }
                    if ($motherName !== '') {
                        $query->orWhere('name', $motherName);
                    }
                })
                ->first();

            if ($parentUser !== null) {
                if (! $dryRun) {
                    $student->forceFill(['parent_user_id' => $parentUser->id])->save();
                    $userRoleService->ensureRoles($parentUser, [UserRole::ORANG_TUA]);
                }
                $repairedParents++;
            }
        }

        // 4. Clean up duplicate / orphan users without profile
        $orphanUsers = User::query()
            ->whereDoesntHave('teacherProfile')
            ->whereDoesntHave('studentProfile')
            ->whereDoesntHave('parentStudents')
            ->where('email', 'not like', '%sarunis.test%') // preserve test accounts
            ->whereJsonDoesntContain('roles', UserRole::ADMIN->value)
            ->whereJsonDoesntContain('roles', UserRole::WAKASEK_KESISWAAN->value)
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->where('email', 'like', 'guru.%')
                        ->orWhere('email', 'like', 'siswa.%')
                        ->orWhere('email', 'like', 'orangtua.%');
                })
                ->orWhere('roles', '[]')
                ->orWhereNull('roles');
            })
            ->get();

        $this->info("Found {$orphanUsers->count()} orphan users without profile.");

        foreach ($orphanUsers as $orphan) {
            if (! $dryRun) {
                $orphan->delete();
            }
            $deletedOrphanUsers++;
        }

        $this->newLine();
        $this->info('=== REPAIR COMPLETE ===');
        $this->info("Mismatched Roles Fixed : {$repairedMismatchedRoles}");
        $this->info("Repaired Teachers      : {$repairedTeachers}");
        $this->info("Repaired Students      : {$repairedStudents}");
        $this->info("Repaired Parents       : {$repairedParents}");
        $this->info("Deleted Orphans        : {$deletedOrphanUsers}");

        return Command::SUCCESS;
    }
}
