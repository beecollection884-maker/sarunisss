<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_role_protected_routes(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
    }

    public function test_admin_can_access_all_role_areas(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'admin sekolah');

        $this->getJson('/api/v1/guru-mapel/dashboard')->assertOk();
        $this->getJson('/api/v1/walikelas/dashboard')->assertOk();
        $this->getJson('/api/v1/orang-tua/dashboard')->assertOk();
        $this->getJson('/api/v1/siswa/dashboard')->assertOk();
    }

    public function test_guru_mapel_only_can_access_guru_mapel_area(): void
    {
        $guruMapel = User::factory()->guruMapel()->create();
        Teacher::query()->create([
            'user_id' => $guruMapel->id,
            'nip' => 'TST-GM-003',
            'name' => $guruMapel->name,
            'is_subject_teacher' => true,
        ]);

        $this->actingAs($guruMapel);

        $this->getJson('/api/v1/guru-mapel/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'guru mapel');

        $this->getJson('/api/v1/walikelas/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/orang-tua/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_guru_mapel_without_homeroom_cannot_access_wali_kelas_area(): void
    {
        $guruMapel = User::factory()->guruMapel()->create();
        // Create teacher profile but without homeroom class assignment
        Teacher::query()->create([
            'user_id' => $guruMapel->id,
            'nip' => 'TST-GM-001',
            'name' => $guruMapel->name,
            'is_subject_teacher' => true,
        ]);

        $this->actingAs($guruMapel);

        $this->getJson('/api/v1/guru-mapel/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'guru mapel');

        // Cannot access walikelas area without homeroom class assignment
        $this->getJson('/api/v1/walikelas/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/orang-tua/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_guru_mapel_with_homeroom_assignment_can_access_wali_kelas_area(): void
    {
        $guru = User::factory()->guruMapel()->create();
        $teacher = Teacher::query()->create([
            'user_id' => $guru->id,
            'nip' => 'TST-GW-001',
            'name' => $guru->name,
            'is_subject_teacher' => true,
        ]);

        // Assign as homeroom teacher to a class
        $schoolClass = SchoolClass::query()->create([
            'name' => '10A',
            'level' => 10,
            'academic_year' => '2025/2026',
            'homeroom_teacher_id' => $teacher->id,
            'description' => 'Kelas 10A',
        ]);

        $this->actingAs($guru);

        // Can access guru_mapel area
        $this->getJson('/api/v1/guru-mapel/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'guru mapel');

        // Can access walikelas area because assigned as homeroom teacher
        $this->getJson('/api/v1/walikelas/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'walikelas');

        $this->getJson('/api/v1/orang-tua/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_siswa_only_can_access_siswa_area(): void
    {
        $siswa = User::factory()->siswa()->create();
        \App\Models\Student::query()->create([
            'user_id' => $siswa->id,
            'nik' => '88888',
            'nisn' => '8888888888',
            'name' => $siswa->name,
            'gender' => 'L',
            'birth_date' => '2010-01-01',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 2',
        ]);

        $this->actingAs($siswa);

        $this->getJson('/api/v1/siswa/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'siswa');

        $this->getJson('/api/v1/guru-mapel/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/walikelas/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/orang-tua/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_orang_tua_only_can_access_orang_tua_area(): void
    {
        $orangTua = User::factory()->orangTua()->create();
        \App\Models\Student::query()->create([
            'parent_user_id' => $orangTua->id,
            'nik' => '99999',
            'nisn' => '9999999999',
            'name' => 'Anak Demo',
            'gender' => 'L',
            'birth_date' => '2010-01-01',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 1',
        ]);

        $this->actingAs($orangTua);

        $this->getJson('/api/v1/orang-tua/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'orang tua');

        $this->getJson('/api/v1/guru-mapel/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/walikelas/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/siswa/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }
}
