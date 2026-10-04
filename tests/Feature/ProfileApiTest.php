<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
        $this->putJson('/api/v1/profile', [])->assertStatus(401);
    }

    public function test_admin_can_view_and_update_own_profile(): void
    {
        $admin = User::factory()->create(['roles' => ['administrator']]);

        $response = $this->actingAs($admin)->getJson('/api/v1/profile');
        $response->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.user.email', $admin->email);

        $updateResponse = $this->actingAs($admin)->putJson('/api/v1/profile', [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@sarunis.test',
            'current_password' => 'password',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Admin Baru',
            'email' => 'admin.baru@sarunis.test',
        ]);
        $this->assertTrue(Hash::check('passwordbaru123', $admin->fresh()->password));
    }

    public function test_teacher_can_view_and_update_own_profile(): void
    {
        $teacherUser = User::factory()->create(['roles' => ['guru_mapel']]);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'nip' => '198010100010',
            'nik' => '3200000000000001',
            'name' => $teacherUser->name,
            'is_subject_teacher' => true,
            'phone' => '0812345678',
            'address' => 'Alamat Guru',
        ]);

        $response = $this->actingAs($teacherUser)->getJson('/api/v1/profile');
        $response->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.profile.nip', '198010100010');

        $updateResponse = $this->actingAs($teacherUser)->putJson('/api/v1/profile', [
            'name' => 'Guru Diperbarui',
            'email' => 'guru.baru@sarunis.test',
            'phone' => '0899999999',
            'address' => 'Alamat Diperbarui',
            'religion' => 'Islam',
            'last_education' => 'S2',
            'major' => 'Pendidikan Matematika',
            'university' => 'UPI',
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $teacherUser->id,
            'name' => 'Guru Diperbarui',
            'email' => 'guru.baru@sarunis.test',
        ]);
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'phone' => '0899999999',
            'address' => 'Alamat Diperbarui',
            'religion' => 'Islam',
            'last_education' => 'S2',
            'university' => 'UPI',
        ]);
    }

    public function test_student_can_view_and_update_only_allowed_fields(): void
    {
        $studentUser = User::factory()->create(['roles' => ['siswa']]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nik' => '1100000000000001',
            'nisn' => '9900000001',
            'name' => $studentUser->name,
            'gender' => 'L',
            'birth_date' => '2010-01-01',
            'phone' => '0822222222',
            'address' => 'Alamat Siswa',
        ]);

        $response = $this->actingAs($studentUser)->getJson('/api/v1/profile');
        $response->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.profile.nisn', '9900000001');

        // Student tries to update name and email, but name change should be ignored and blocked for security
        $updateResponse = $this->actingAs($studentUser)->putJson('/api/v1/profile', [
            'name' => 'Siswa Mencoba Ganti Nama',
            'email' => 'siswa.baru@sarunis.test',
            'phone' => '0833333333',
            'address' => 'Alamat Baru Siswa',
        ]);

        $updateResponse->assertOk();
        // The user's name should NOT change (stays original)
        $this->assertDatabaseHas('users', [
            'id' => $studentUser->id,
            'name' => $studentUser->name, // unchanged!
            'email' => 'siswa.baru@sarunis.test', // email is allowed to change
        ]);
        // The student's phone and address should update
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'phone' => '0833333333',
            'address' => 'Alamat Baru Siswa',
            'name' => $student->name, // unchanged!
        ]);
    }
}
