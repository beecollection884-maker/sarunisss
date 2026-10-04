<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function page(Request $request): View
    {
        $announcements = Announcement::query()
            ->with('creator')
            ->latest()
            ->get();

        // Map target roles labels
        $announcements->transform(function (Announcement $announcement) {
            $announcement->target_roles_label = $announcement->target_roles 
                ? implode(', ', array_map(fn($role) => ucwords(str_replace('_', ' ', $role)), $announcement->target_roles))
                : 'Semua Role';
            return $announcement;
        });

        $announcementPayload = $announcements->mapWithKeys(fn(Announcement $announcement): array => [
            $announcement->id => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'target_roles' => $announcement->target_roles ?? [],
            ],
        ])->all();

        return view('dashboard.admin-announcements', [
            'pageTitle' => 'Pengumuman',
            'menuSections' => $this->adminPageMenu('pengumuman'),
            'announcements' => $announcements,
            'announcementPayload' => $announcementPayload,
        ]);
    }

    public function index(): JsonResponse
    {
        $announcements = Announcement::query()
            ->with('creator')
            ->latest()
            ->get();

        return response()->json([
            'data' => $announcements,
        ]);
    }

    public function store(UpsertAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $announcement = Announcement::create($data);

        return response()->json([
            'message' => 'Pengumuman berhasil dibuat.',
            'data' => $announcement->load('creator'),
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => $announcement->load('creator'),
        ]);
    }

    public function update(UpsertAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validated();
        $announcement->update($data);

        return response()->json([
            'message' => 'Pengumuman berhasil diperbarui.',
            'data' => $announcement->load('creator'),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json([
            'message' => 'Pengumuman berhasil dihapus.',
        ]);
    }

    protected function adminPageMenu(string $activeItem): array
    {
        return [
            [
                'title' => 'Menu',
                'items' => [
                    ['label' => 'Beranda', 'icon' => 'home', 'href' => url('/admin/dashboard'), 'active' => $activeItem === 'beranda'],
                    ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/admin/data-siswa'), 'active' => $activeItem === 'data-siswa'],
                    ['label' => 'Data Guru', 'icon' => 'teacher', 'href' => url('/admin/data-guru'), 'active' => $activeItem === 'data-guru'],
                    ['label' => 'Data Kelas', 'icon' => 'class', 'href' => url('/admin/data-kelas'), 'active' => $activeItem === 'data-kelas'],
                    ['label' => 'Mata Pelajaran', 'icon' => 'subject', 'href' => url('/admin/mata-pelajaran'), 'active' => $activeItem === 'mata-pelajaran'],
                    ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/admin/kalender-akademik'), 'active' => $activeItem === 'kalender-akademik'],
                    ['label' => 'Jadwal Pelajaran', 'icon' => 'schedule', 'href' => url('/admin/schedule/generate'), 'active' => $activeItem === 'jadwal-pelajaran'],
                    ['label' => 'Rekap Kehadiran', 'icon' => 'recap', 'href' => url('/admin/rekap-kehadiran'), 'active' => $activeItem === 'rekap-kehadiran'],
                    ['label' => 'Laporan Statistik', 'icon' => 'chart', 'href' => url('/admin/laporan-statistik'), 'active' => $activeItem === 'laporan-tren'],
                ],
            ],
            [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Manajemen Pengguna', 'icon' => 'users', 'href' => url('/admin/manajemen-pengguna'), 'active' => $activeItem === 'manajemen-pengguna'],
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => url('/admin/pengaturan'), 'active' => $activeItem === 'pengaturan'],
                    ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/admin/catatan-siswa'), 'active' => $activeItem === 'catatan-siswa'],
                    ['label' => 'Pengumuman', 'icon' => 'announcement', 'href' => url('/admin/pengumuman'), 'active' => $activeItem === 'pengumuman'],
                ],
            ],
        ];
    }
}
