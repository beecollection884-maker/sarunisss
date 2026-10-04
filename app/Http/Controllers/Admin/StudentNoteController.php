<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentNoteController extends Controller
{
    public function page(Request $request): View
    {
        return view('dashboard.admin-student-notes', [
            'pageTitle' => 'Catatan Siswa',
            'menuSections' => $this->adminPageMenu('catatan-siswa'),
            'notes' => StudentNote::query()
                ->with(['student.schoolClass', 'teacher', 'user'])
                ->latest()
                ->get(),
            'students' => Student::query()->with('schoolClass')->orderBy('name')->get(),
            'teachers' => Teacher::query()->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
        ]);

        $notes = StudentNote::query()
            ->with(['student.schoolClass', 'teacher', 'user'])
            ->when($filters['student_id'] ?? null, fn ($query, int $studentId) => $query->where('student_id', $studentId))
            ->when($filters['teacher_id'] ?? null, fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when(($filters['status'] ?? null) === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when(($filters['status'] ?? null) === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);
        $payload['user_id'] = $request->user()->id;

        $note = StudentNote::query()->create($payload);

        return response()->json([
            'message' => 'Catatan siswa berhasil dibuat.',
            'data' => $note->load(['student.schoolClass', 'teacher', 'user']),
        ], 201);
    }

    public function show(StudentNote $catatanSiswa): JsonResponse
    {
        return response()->json([
            'data' => $catatanSiswa->load(['student.schoolClass', 'teacher', 'user']),
        ]);
    }

    public function update(Request $request, StudentNote $catatanSiswa): JsonResponse
    {
        $catatanSiswa->update($this->validated($request));

        return response()->json([
            'message' => 'Catatan siswa berhasil diperbarui.',
            'data' => $catatanSiswa->refresh()->load(['student.schoolClass', 'teacher', 'user']),
        ]);
    }

    public function destroy(StudentNote $catatanSiswa): JsonResponse
    {
        $catatanSiswa->delete();

        return response()->json([
            'message' => 'Catatan siswa berhasil dihapus.',
        ]);
    }

    public function homeroomPage(Request $request): View
    {
        $teacher = $request->user()->teacherProfile;
        $studentQuery = Student::query()->with('schoolClass')->orderBy('name');
        $notesQuery = StudentNote::query()->with(['student.schoolClass', 'teacher', 'user'])->latest();

        if (! $request->user()->hasRole('admin')) {
            abort_if($teacher === null, 403, 'Profil guru tidak ditemukan.');
            $classIds = $teacher->homeroomClasses()->pluck('id');
            $studentQuery->whereIn('school_class_id', $classIds);
            $notesQuery->whereHas('student', fn ($query) => $query->whereIn('school_class_id', $classIds));
        }

        return view('dashboard.homeroom-student-notes', [
            'pageTitle' => 'Catatan Siswa',
            'menuSections' => $this->homeroomPageMenu('catatan-siswa'),
            'notes' => $notesQuery->get(),
            'students' => $studentQuery->get(),
            'teacher' => $teacher,
        ]);
    }

    public function homeroomIndex(Request $request): JsonResponse
    {
        $studentIds = $this->homeroomStudentIds($request);

        return response()->json(
            StudentNote::query()
                ->with(['student.schoolClass', 'teacher', 'user'])
                ->whereIn('student_id', $studentIds)
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function homeroomStore(Request $request): JsonResponse
    {
        $payload = $this->validated($request);
        $studentIds = $this->homeroomStudentIds($request);

        abort_unless($studentIds->contains((int) $payload['student_id']), 403, 'Siswa bukan bagian dari kelas perwalian.');

        $payload['user_id'] = $request->user()->id;
        $payload['teacher_id'] = $request->user()->teacherProfile?->id ?? $payload['teacher_id'] ?? null;

        $note = StudentNote::query()->create($payload);

        return response()->json([
            'message' => 'Catatan siswa berhasil dibuat.',
            'data' => $note->load(['student.schoolClass', 'teacher', 'user']),
        ], 201);
    }

    public function homeroomShow(Request $request, StudentNote $catatanSiswa): JsonResponse
    {
        $this->authorizeHomeroomNote($request, $catatanSiswa);

        return response()->json([
            'data' => $catatanSiswa->load(['student.schoolClass', 'teacher', 'user']),
        ]);
    }

    public function homeroomUpdate(Request $request, StudentNote $catatanSiswa): JsonResponse
    {
        $this->authorizeHomeroomNote($request, $catatanSiswa);
        $payload = $this->validated($request);
        $studentIds = $this->homeroomStudentIds($request);

        abort_unless($studentIds->contains((int) $payload['student_id']), 403, 'Siswa bukan bagian dari kelas perwalian.');

        $payload['teacher_id'] = $request->user()->teacherProfile?->id ?? $payload['teacher_id'] ?? null;
        $catatanSiswa->update($payload);

        return response()->json([
            'message' => 'Catatan siswa berhasil diperbarui.',
            'data' => $catatanSiswa->refresh()->load(['student.schoolClass', 'teacher', 'user']),
        ]);
    }

    public function homeroomDestroy(Request $request, StudentNote $catatanSiswa): JsonResponse
    {
        $this->authorizeHomeroomNote($request, $catatanSiswa);
        $catatanSiswa->delete();

        return response()->json(['message' => 'Catatan siswa berhasil dihapus.']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'min:3', 'max:5000'],
            'follow_up_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date'],
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

    /**
     * @return Collection<int, int>
     */
    protected function homeroomStudentIds(Request $request): Collection
    {
        if ($request->user()->hasRole('admin')) {
            return Student::query()->pluck('id');
        }

        $teacher = $request->user()->teacherProfile;
        abort_if($teacher === null, 403, 'Profil guru tidak ditemukan.');

        return Student::query()
            ->whereIn('school_class_id', $teacher->homeroomClasses()->pluck('id'))
            ->pluck('id');
    }

    protected function authorizeHomeroomNote(Request $request, StudentNote $note): void
    {
        abort_unless($this->homeroomStudentIds($request)->contains((int) $note->student_id), 403, 'Catatan ini bukan bagian dari kelas perwalian.');
    }

    protected function homeroomPageMenu(string $activeItem): array
    {
        return [
            [
                'title' => 'Menu',
                'items' => [
                    ['label' => 'Beranda', 'icon' => 'home', 'href' => url('/walikelas/dashboard'), 'active' => false],
                    ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/walikelas/data-siswa'), 'active' => false],
                    ['label' => 'Absensi Kelas', 'icon' => 'attendance', 'href' => url('/walikelas/absensi-kelas'), 'active' => false],
                    ['label' => 'Rekap Absensi', 'icon' => 'recap', 'href' => url('/walikelas/rekap-absensi'), 'active' => false],
                    ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/walikelas/catatan-siswa'), 'active' => $activeItem === 'catatan-siswa'],
                ],
            ],
            [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => url('/admin/pengaturan'), 'active' => false],
                ],
            ],
        ];
    }
}
