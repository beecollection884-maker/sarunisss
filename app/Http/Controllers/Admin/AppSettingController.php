<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function __construct(
        protected AppSettingService $appSettingService,
    ) {
    }

    public function page(): View
    {
        $this->appSettingService->ensureDefaults();

        return view('dashboard.admin-settings', [
            'pageTitle' => 'Pengaturan',
            'menuSections' => $this->adminPageMenu('pengaturan'),
            'settings' => AppSetting::query()->orderBy('label')->get(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->appSettingService->ensureDefaults();

        return response()->json([
            'data' => AppSetting::query()->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);
        $setting = AppSetting::query()->create($payload);
        $this->appSettingService->clearCache();

        return response()->json([
            'message' => 'Pengaturan berhasil dibuat.',
            'data' => $setting,
        ], 201);
    }

    public function show(AppSetting $setting): JsonResponse
    {
        return response()->json(['data' => $setting]);
    }

    public function update(Request $request, AppSetting $setting): JsonResponse
    {
        $payload = $this->validated($request, $setting);
        $setting->update($payload);
        $this->appSettingService->clearCache();

        return response()->json([
            'message' => 'Pengaturan berhasil diperbarui.',
            'data' => $setting->refresh(),
        ]);
    }

    public function destroy(AppSetting $setting): JsonResponse
    {
        $setting->delete();
        $this->appSettingService->clearCache();

        return response()->json(['message' => 'Pengaturan berhasil dihapus.']);
    }

    protected function validated(Request $request, ?AppSetting $setting = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('app_settings', 'key')->ignore($setting?->id)],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(['text', 'number', 'boolean', 'textarea', 'email', 'url', 'select', 'date'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Simplified update: only validate & update the value field.
     * Used by frontend which only sends { value } without all metadata.
     */
    public function updateValue(Request $request, AppSetting $setting): JsonResponse
    {
        $request->validate([
            'value' => ['nullable', 'string', 'max:5000'],
        ]);

        $setting->update(['value' => $request->input('value')]);
        $this->appSettingService->clearCache();

        return response()->json([
            'message' => 'Pengaturan berhasil diperbarui.',
            'data'    => $setting->refresh(),
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

