<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineAttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/attendance/offline/unsynced')
            ->assertStatus(401);

        $this->getJson('/api/attendance/offline/student/1/2026-08-16')
            ->assertStatus(401);

        $this->postJson('/api/attendance/offline/record', [
            'offline_device_id' => 'device-test',
            'student_id' => 1,
            'teacher_id' => 1,
            'school_class_id' => 1,
            'attendance_type' => 'class',
            'attendance_date' => '2026-08-16',
            'status' => 'hadir',
        ])->assertStatus(401);

        $this->deleteJson('/api/attendance/offline/clear-old')
            ->assertStatus(401);
    }

    public function test_device_token_authentication_allows_access(): void
    {
        config(['app.offline_device_token' => 'secret-device-token-123']);

        $response = $this->withHeaders([
            'X-Device-Token' => 'secret-device-token-123',
        ])->getJson('/api/attendance/offline/unsynced');

        $response->assertStatus(200);
    }

    public function test_invalid_device_token_is_rejected(): void
    {
        config(['app.offline_device_token' => 'secret-device-token-123']);

        $response = $this->withHeaders([
            'X-Device-Token' => 'wrong-token',
        ])->getJson('/api/attendance/offline/unsynced');

        $response->assertStatus(401);
    }

    public function test_unauthorized_student_role_is_rejected_with_403(): void
    {
        $studentUser = User::factory()->create([
            'roles' => [UserRole::SISWA->value],
        ]);

        $this->actingAs($studentUser)
            ->getJson('/api/attendance/offline/unsynced')
            ->assertStatus(403);

        $this->actingAs($studentUser)
            ->deleteJson('/api/attendance/offline/clear-old')
            ->assertStatus(403);
    }

    public function test_teacher_can_access_operational_endpoints_but_not_admin_maintenance(): void
    {
        $teacherUser = User::factory()->create([
            'roles' => [UserRole::GURU_MAPEL->value],
        ]);

        $this->actingAs($teacherUser)
            ->getJson('/api/attendance/offline/unsynced')
            ->assertStatus(200);

        $this->actingAs($teacherUser)
            ->deleteJson('/api/attendance/offline/clear-old')
            ->assertStatus(403);

        $this->actingAs($teacherUser)
            ->getJson('/api/attendance/offline/statistics')
            ->assertStatus(403);
    }

    public function test_admin_can_access_maintenance_and_statistics(): void
    {
        $adminUser = User::factory()->create([
            'roles' => ['admin_sekolah'],
        ]);

        $this->actingAs($adminUser)
            ->getJson('/api/attendance/offline/statistics')
            ->assertStatus(200);

        $this->actingAs($adminUser)
            ->deleteJson('/api/attendance/offline/clear-old')
            ->assertStatus(200);
    }
}
