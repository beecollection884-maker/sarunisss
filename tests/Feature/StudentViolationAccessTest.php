<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\User;
use App\Enums\UserRole;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentViolationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = DatabaseSeeder::class;

    public function test_admin_can_access_student_violations_page(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/pelanggaran');

        $response->assertOk();
        $response->assertJsonPath('activePortal', 'admin');
    }

    public function test_wakasek_kesiswaan_can_access_student_violations_page(): void
    {
        $wakasek = User::factory()->withRoles([UserRole::WAKASEK_KESISWAAN])->create();

        $response = $this->actingAs($wakasek)->getJson('/api/v1/wakasek-kesiswaan/pelanggaran');

        $response->assertOk();
        $response->assertJsonPath('activePortal', 'wakasek-kesiswaan');
    }

    public function test_guru_piket_can_access_student_violations_page(): void
    {
        $guruPiket = User::factory()->withRoles([UserRole::GURU_PIKET])->create();

        $response = $this->actingAs($guruPiket)->getJson('/api/v1/guru-piket/pelanggaran');

        $response->assertOk();
        $response->assertJsonPath('activePortal', 'guru-piket');
    }

    public function test_unauthorized_user_cannot_access_student_violations_page(): void
    {
        $siswa = User::factory()->siswa()->create();

        $this->actingAs($siswa)->getJson('/api/v1/admin/pelanggaran')->assertStatus(403);
        $this->actingAs($siswa)->getJson('/api/v1/wakasek-kesiswaan/pelanggaran')->assertStatus(403);
        $this->actingAs($siswa)->getJson('/api/v1/guru-piket/pelanggaran')->assertStatus(403);
    }

    public function test_wakasek_kesiswaan_can_crud_violations(): void
    {
        $wakasek = User::factory()->withRoles([UserRole::WAKASEK_KESISWAAN])->create();
        $student = Student::firstOrFail();

        $payload = [
            'student_id' => $student->id,
            'violation_date' => '2026-07-05',
            'violation_type' => 'Keterlambatan',
            'description' => 'Datang terlambat 15 menit',
            'points' => 5,
            'action_taken' => 'Teguran lisan',
        ];

        // Store
        $response = $this->actingAs($wakasek)
            ->postJson('/api/v1/wakasek-kesiswaan/pelanggaran', $payload);

        $response->assertCreated();
        
        $violation = StudentViolation::where('student_id', $student->id)
            ->where('violation_type', 'Keterlambatan')
            ->where('description', 'Datang terlambat 15 menit')
            ->firstOrFail();

        $this->assertEquals($wakasek->id, $violation->reported_by_id);

        // Update
        $updatePayload = array_merge($payload, [
            'description' => 'Datang terlambat 30 menit',
            'points' => 10,
        ]);

        $response = $this->actingAs($wakasek)
            ->putJson('/api/v1/wakasek-kesiswaan/pelanggaran/' . $violation->id, $updatePayload);

        $response->assertOk();
        $this->assertEquals(10, $violation->fresh()->points);
        $this->assertEquals('Datang terlambat 30 menit', $violation->fresh()->description);

        // Delete
        $response = $this->actingAs($wakasek)
            ->deleteJson('/api/v1/wakasek-kesiswaan/pelanggaran/' . $violation->id);

        $response->assertOk();
        $this->assertNull(StudentViolation::find($violation->id));
    }

    public function test_guru_piket_can_crud_violations(): void
    {
        $guruPiket = User::factory()->withRoles([UserRole::GURU_PIKET])->create();
        $student = Student::firstOrFail();

        $payload = [
            'student_id' => $student->id,
            'violation_date' => '2026-07-05',
            'violation_type' => 'Ketertiban Berpakaian',
            'description' => 'Tidak memakai dasi sekolah',
            'points' => 3,
            'action_taken' => 'Diberikan dasi cadangan',
        ];

        // Store
        $response = $this->actingAs($guruPiket)
            ->postJson('/api/v1/guru-piket/pelanggaran', $payload);

        $response->assertCreated();
        
        $violation = StudentViolation::where('student_id', $student->id)
            ->where('violation_type', 'Ketertiban Berpakaian')
            ->where('description', 'Tidak memakai dasi sekolah')
            ->firstOrFail();

        $this->assertEquals($guruPiket->id, $violation->reported_by_id);

        // Update
        $updatePayload = array_merge($payload, [
            'points' => 5,
        ]);

        $response = $this->actingAs($guruPiket)
            ->putJson('/api/v1/guru-piket/pelanggaran/' . $violation->id, $updatePayload);

        $response->assertOk();
        $this->assertEquals(5, $violation->fresh()->points);

        // Delete
        $response = $this->actingAs($guruPiket)
            ->deleteJson('/api/v1/guru-piket/pelanggaran/' . $violation->id);

        $response->assertOk();
        $this->assertNull(StudentViolation::find($violation->id));
    }
}
