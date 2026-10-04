<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = DatabaseSeeder::class;

    /**
     * Test admin can view announcement page.
     */
    public function test_admin_can_view_announcement_page(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get('/admin/pengumuman');

        $response->assertStatus(200);
        $response->assertSee('Pengumuman');
    }

    /**
     * Test admin can create announcement.
     */
    public function test_admin_can_create_announcement(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $payload = [
            'title' => 'Pengumuman Penting Ujian Akhir',
            'content' => 'Isi detail pengumuman mengenai tata tertib ujian semester genap.',
            'target_roles' => ['siswa', 'orang_tua'],
        ];

        $response = $this->actingAs($admin)
            ->postJson('/admin/announcements', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Pengumuman Penting Ujian Akhir');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Pengumuman Penting Ujian Akhir',
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Test admin can update announcement.
     */
    public function test_admin_can_update_announcement(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $announcement = Announcement::create([
            'title' => 'Judul Lama',
            'content' => 'Konten lama pengumuman',
            'target_roles' => ['guru_mapel'],
            'created_by' => $admin->id,
        ]);

        $payload = [
            'title' => 'Judul Baru',
            'content' => 'Konten baru yang sudah diperbarui.',
            'target_roles' => ['admin'],
        ];

        $response = $this->actingAs($admin)
            ->putJson("/admin/announcements/{$announcement->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Judul Baru');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Judul Baru',
        ]);
    }

    /**
     * Test admin can delete announcement.
     */
    public function test_admin_can_delete_announcement(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $announcement = Announcement::create([
            'title' => 'Pengumuman Dihapus',
            'content' => 'Isi pengumuman yang akan segera dihapus.',
            'target_roles' => null,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/admin/announcements/{$announcement->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    /**
     * Test announcements visibility rules for different roles.
     */
    public function test_announcement_visibility_based_on_target_roles(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $siswa = User::where('email', 'siswa@sarunis.test')->firstOrFail();
        $guru = User::where('email', 'guru.mapel@sarunis.test')->firstOrFail();

        // 1. Pengumuman khusus siswa
        $studentAnnouncement = Announcement::create([
            'title' => 'Khusus Siswa',
            'content' => 'Isi pengumuman khusus siswa',
            'target_roles' => ['siswa'],
            'created_by' => $admin->id,
        ]);

        // 2. Pengumuman untuk semua
        $publicAnnouncement = Announcement::create([
            'title' => 'Info Umum',
            'content' => 'Isi pengumuman umum untuk semua orang',
            'target_roles' => null,
            'created_by' => $admin->id,
        ]);

        // Uji untuk Siswa: harus melihat kedua pengumuman
        $responseSiswa = $this->actingAs($siswa)->get('/siswa/dashboard');
        $responseSiswa->assertStatus(200);
        $responseSiswa->assertSee('Khusus Siswa');
        $responseSiswa->assertSee('Info Umum');

        // Uji untuk Guru: hanya melihat pengumuman umum, tidak melihat pengumuman khusus siswa
        $responseGuru = $this->actingAs($guru)->get('/guru-mapel/dashboard');
        $responseGuru->assertStatus(200);
        $responseGuru->assertDontSee('Khusus Siswa');
        $responseGuru->assertSee('Info Umum');
    }
}
