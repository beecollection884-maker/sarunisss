<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Services\AcademicCalendarService;
use App\Services\AppSettingService;
use App\Services\SemesterLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicCalendarController extends Controller
{
    public function __construct(
        protected AcademicCalendarService $academicCalendarService,
        protected AppSettingService $appSettingService,
        protected SemesterLockService $semesterLockService,
    ) {
    }

    public function page(): View
    {
        $this->appSettingService->ensureDefaults();
        $academicYear = $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
        $semester = $this->appSettingService->value('active_semester', 'ganjil') ?: 'ganjil';

        return view('dashboard.admin-academic-calendar', [
            'pageTitle' => 'Kalender Akademik',
            'menuSections' => $this->adminPageMenu('kalender-akademik'),
            'activeAcademicYear' => $academicYear,
            'activeSemester' => $semester,
            'eventTypes' => AcademicCalendarService::TYPES,
            'semesterLock' => $this->semesterLockService->get($academicYear, $semester),
            'events' => $this->academicCalendarService->list([
                'academic_year' => $academicYear,
                'semester' => $semester,
            ]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json([
            'data' => $this->academicCalendarService->list($filters),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);
        $payload['created_by'] = $request->user()->id;

        $calendar = $this->academicCalendarService->create($payload);

        return response()->json([
            'message' => 'Kalender akademik berhasil dibuat.',
            'data' => $calendar->load('createdBy:id,name,email'),
        ], 201);
    }

    public function show(AcademicCalendar $kalenderAkademik): JsonResponse
    {
        return response()->json([
            'data' => $kalenderAkademik->load('createdBy:id,name,email'),
        ]);
    }

    public function update(Request $request, AcademicCalendar $kalenderAkademik): JsonResponse
    {
        $calendar = $this->academicCalendarService->update($kalenderAkademik, $this->validated($request));

        return response()->json([
            'message' => 'Kalender akademik berhasil diperbarui.',
            'data' => $calendar->load('createdBy:id,name,email'),
        ]);
    }

    public function destroy(AcademicCalendar $kalenderAkademik): JsonResponse
    {
        $this->academicCalendarService->delete($kalenderAkademik);

        return response()->json([
            'message' => 'Kalender akademik berhasil dihapus.',
        ]);
    }

    protected function filters(Request $request): array
    {
        return $request->validate([
            'academic_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'category' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(AcademicCalendarService::TYPES))],
            'is_holiday' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(AcademicCalendarService::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_holiday' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
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
