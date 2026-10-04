<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_attendance_recap_endpoint_returns_ok_for_json(): void
    {
        $admin = User::factory()->create([
            'roles' => [UserRole::ADMIN->value],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/absensi/rekap');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'apiPayload' => ['area', 'akses'],
            'profile',
            'calendar',
        ]);
    }

    public function test_admin_attendance_report_endpoint_returns_ok_for_json(): void
    {
        $admin = User::factory()->create([
            'roles' => [UserRole::ADMIN->value],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/absensi/laporan');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'apiPayload' => ['area', 'akses'],
            'profile',
            'calendar',
        ]);
    }

    public function test_admin_attendance_recap_endpoint_falls_back_to_app_view_for_html(): void
    {
        $admin = User::factory()->create([
            'roles' => [UserRole::ADMIN->value],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->get('/admin/absensi/rekap');

        $response->assertStatus(200);
        $response->assertViewIs('app');
    }
}
