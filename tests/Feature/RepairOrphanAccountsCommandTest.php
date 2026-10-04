<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairOrphanAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_orphan_teachers(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'guru.1985010112345678@sarunis.local',
            'roles' => [UserRole::GURU_MAPEL->value],
        ]);

        $teacher = Teacher::create([
            'user_id' => null,
            'name' => 'Budi Santoso',
            'nip' => '1985010112345678',
        ]);

        $this->artisan('sarunis:repair-orphan-accounts')
            ->assertExitCode(0);

        $teacher->refresh();
        $this->assertEquals($user->id, $teacher->user_id);
    }

    public function test_repair_orphan_students(): void
    {
        $user = User::factory()->create([
            'name' => 'Ahmad Siswa',
            'email' => 'siswa.1234567890@sarunis.local',
            'roles' => [UserRole::SISWA->value],
        ]);

        $student = Student::create([
            'user_id' => null,
            'name' => 'Ahmad Siswa',
            'nisn' => '1234567890',
            'nik' => '3201123456789001',
        ]);

        $this->artisan('sarunis:repair-orphan-accounts')
            ->assertExitCode(0);

        $student->refresh();
        $this->assertEquals($user->id, $student->user_id);
    }

    public function test_cleanup_orphan_users_without_profile(): void
    {
        $orphanUser = User::factory()->create([
            'name' => 'Orphan User',
            'email' => 'guru.19850101.2@sarunis.local',
            'roles' => [UserRole::GURU_MAPEL->value],
        ]);

        $this->artisan('sarunis:repair-orphan-accounts')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', [
            'id' => $orphanUser->id,
        ]);
    }

    public function test_deleting_student_cleans_up_orphaned_parent_account(): void
    {
        $parentUser = User::factory()->create([
            'name' => 'Orang Tua QA',
            'email' => 'orangtua.qa@sarunis.local',
            'roles' => [UserRole::ORANG_TUA->value],
        ]);

        $studentUser = User::factory()->create([
            'name' => 'Siswa QA',
            'email' => 'siswa.qa@sarunis.local',
            'roles' => [UserRole::SISWA->value],
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'parent_user_id' => $parentUser->id,
            'name' => 'Siswa QA',
            'nisn' => '9988776655',
            'nik' => '3201998877665500',
        ]);

        $service = app(\App\Services\StudentService::class);
        $service->delete($student);

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $parentUser->id]);
    }
}
