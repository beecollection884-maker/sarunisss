<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserRoleService $userRoleService,
    ) {
    }

    public function page(Request $request): View
    {
        return view('dashboard.admin-users', [
            'pageTitle' => 'Manajemen Pengguna',
            'menuSections' => $this->adminPageMenu('manajemen-pengguna'),
            'users' => User::query()
                ->with(['teacherProfile', 'studentProfile'])
                ->orderBy('name')
                ->get(),
            'roleOptions' => UserRole::values(),
            'teacherOptions' => Teacher::query()->with('user')->orderBy('name')->get(),
            'studentOptions' => Student::query()->with('user')->orderBy('name')->get(),
            'migrationCounts' => [
                'teachers' => Teacher::query()->whereNull('user_id')->count(),
                'students' => Student::query()->whereNull('user_id')->count(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 1000);
        $search = trim((string) $request->input('search', ''));

        $query = User::query()
            ->with($this->userRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereJsonContains('roles', $search)
                        ->orWhereHas('teacherProfile', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('nip', 'like', "%{$search}%")
                                ->orWhere('nik', 'like', "%{$search}%");
                        })
                        ->orWhereHas('studentProfile', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%")
                                ->orWhere('nik', 'like', "%{$search}%");
                        });
                });
            });

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(UserRole::values())],
            'email_verified' => ['nullable', 'boolean'],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
        ]);

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'roles' => array_values(array_unique($payload['roles'] ?? [])),
        ]);
        $user->forceFill([
            'email_verified_at' => ($payload['email_verified'] ?? true) ? now() : null,
        ])->save();
        $this->syncProfiles($user, $payload);

        return response()->json([
            'message' => 'Pengguna berhasil dibuat.',
            'data' => $user->load($this->userRelations()),
        ], 201);
    }

    public function show(User $pengguna): JsonResponse
    {
        return response()->json([
            'data' => $pengguna->load($this->userRelations()),
        ]);
    }

    public function update(Request $request, User $pengguna): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(UserRole::values())],
            'email_verified' => ['nullable', 'boolean'],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
        ]);

        if ($pengguna->hasRole(UserRole::ADMIN) && ! in_array(UserRole::ADMIN->value, $payload['roles'] ?? [], true) && $this->adminCount() <= 1) {
            abort(422, 'Role admin terakhir tidak dapat dicabut.');
        }

        $data = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'roles' => array_values(array_unique($payload['roles'] ?? [])),
            'email_verified_at' => ($payload['email_verified'] ?? $pengguna->email_verified_at !== null) ? ($pengguna->email_verified_at ?? now()) : null,
        ];

        if (($payload['password'] ?? null) !== null) {
            $data['password'] = $payload['password'];
        }

        $pengguna->forceFill($data)->save();
        $this->syncProfiles($pengguna, $payload);

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => $pengguna->refresh()->load($this->userRelations()),
        ]);
    }

    public function destroy(Request $request, User $pengguna): JsonResponse
    {
        abort_if($request->user()->is($pengguna), 422, 'Akun yang sedang digunakan tidak dapat dihapus.');
        abort_if($pengguna->hasRole(UserRole::ADMIN) && $this->adminCount() <= 1, 422, 'Admin terakhir tidak dapat dihapus.');

        $pengguna->delete();

        return response()->json([
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }

    public function migrateProfiles(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source' => ['required', 'string', Rule::in(['all', 'teachers', 'students'])],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $source = $payload['source'];
        $emailVerified = (bool) ($payload['email_verified'] ?? true);

        $result = DB::transaction(function () use ($source, $emailVerified): array {
            $createdTeachers = $source !== 'students'
                ? $this->migrateTeachers($emailVerified)
                : [];
            $createdStudents = $source !== 'teachers'
                ? $this->migrateStudents($emailVerified)
                : [];

            return [
                'teachers_created' => count($createdTeachers),
                'students_created' => count($createdStudents),
                'total_created' => count($createdTeachers) + count($createdStudents),
                'teachers' => $createdTeachers,
                'students' => $createdStudents,
            ];
        });

        return response()->json([
            'message' => $result['total_created'] > 0
                ? "{$result['total_created']} akun pengguna berhasil dibuat dari data profil."
                : 'Tidak ada data guru atau siswa yang perlu dimigrasi.',
            'data' => [
                ...$result,
                'users' => User::query()
                    ->with($this->userRelations())
                    ->orderByDesc('created_at')
                    ->limit($result['total_created'])
                    ->get()
                    ->reverse()
                    ->values(),
            ],
        ]);
    }

    /**
     * @return array<int, array{title:string,items:array<int, array{label:string,icon:string,href:string,active:bool}>}>
     */
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
     * @return array<int, string>
     */
    protected function userRelations(): array
    {
        return [
            'teacherProfile',
            'studentProfile.schoolClass',
            'parentStudents.schoolClass',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function syncProfiles(User $user, array $payload): void
    {
        $roles = $user->roles ?? [];
        $hasTeacherRole = in_array(UserRole::GURU_MAPEL->value, $roles, true) || in_array(UserRole::GURU_PIKET->value, $roles, true);
        $hasStudentRole = in_array(UserRole::SISWA->value, $roles, true);

        // 1. TEACHER PROFILE SYNC
        $teacherId = $payload['teacher_id'] ?? null;

        if ($teacherId !== null) {
            Teacher::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($teacherId)
                ->update(['user_id' => null]);

            $teacher = Teacher::query()->findOrFail($teacherId);
            abort_if($teacher->user_id !== null && (int) $teacher->user_id !== (int) $user->id, 422, 'Profil guru sudah terhubung ke pengguna lain.');
            $teacher->forceFill(['user_id' => $user->id])->save();
            $teacher->load('user');
            $this->userRoleService->syncTeacherRoles($teacher);
        } else {
            $existingTeacher = Teacher::query()->where('user_id', $user->id)->first();
            if ($existingTeacher !== null) {
                $this->userRoleService->syncTeacherRoles($existingTeacher);
            } elseif ($hasTeacherRole || str_starts_with($user->email, 'guru.')) {
                $matchedTeacher = Teacher::query()
                    ->whereNull('user_id')
                    ->where(function ($query) use ($user): void {
                        $query->where('name', $user->name);
                        $parts = explode('@', $user->email);
                        $emailLocal = $parts[0] ?? '';
                        if (str_starts_with($emailLocal, 'guru.')) {
                            $identifier = substr($emailLocal, 5);
                            $query->orWhere('nip', $identifier)->orWhere('nik', $identifier);
                        }
                    })
                    ->first();

                if ($matchedTeacher !== null) {
                    $matchedTeacher->forceFill(['user_id' => $user->id])->save();
                    $this->userRoleService->syncTeacherRoles($matchedTeacher);
                }
            }
        }

        // 2. STUDENT PROFILE SYNC
        $studentId = $payload['student_id'] ?? null;

        if ($studentId !== null) {
            Student::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($studentId)
                ->update(['user_id' => null]);

            $student = Student::query()->findOrFail($studentId);
            abort_if($student->user_id !== null && (int) $student->user_id !== (int) $user->id, 422, 'Profil siswa sudah terhubung ke pengguna lain.');
            $student->forceFill(['user_id' => $user->id])->save();
            $student->load('user');
            $this->userRoleService->syncStudentRole($student);
        } else {
            $existingStudent = Student::query()->where('user_id', $user->id)->first();
            if ($existingStudent !== null) {
                $this->userRoleService->syncStudentRole($existingStudent);
            } elseif ($hasStudentRole || str_starts_with($user->email, 'siswa.')) {
                $matchedStudent = Student::query()
                    ->whereNull('user_id')
                    ->where(function ($query) use ($user): void {
                        $query->where('name', $user->name);
                        $parts = explode('@', $user->email);
                        $emailLocal = $parts[0] ?? '';
                        if (str_starts_with($emailLocal, 'siswa.')) {
                            $identifier = substr($emailLocal, 6);
                            $query->orWhere('nisn', $identifier)->orWhere('nik', $identifier);
                        }
                    })
                    ->first();

                if ($matchedStudent !== null) {
                    $matchedStudent->forceFill(['user_id' => $user->id])->save();
                    $this->userRoleService->syncStudentRole($matchedStudent);
                }
            }
        }
    }

    protected function adminCount(): int
    {
        return User::query()
            ->whereJsonContains('roles', UserRole::ADMIN->value)
            ->count();
    }

    /**
     * @return array<int, array{name:string,email:string,password:string}>
     */
    protected function migrateTeachers(bool $emailVerified): array
    {
        return Teacher::query()
            ->with(['subjects', 'teachingAssignments', 'homeroomClasses'])
            ->whereNull('user_id')
            ->orderBy('name')
            ->get()
            ->map(function (Teacher $teacher) use ($emailVerified): array {
                $password = $this->defaultTeacherPassword($teacher);
                $user = User::query()->create([
                    'name' => $teacher->name,
                    'email' => $this->uniqueGeneratedEmail('guru', $teacher),
                    'password' => $password,
                    'roles' => [],
                ]);
                $user->forceFill([
                    'email_verified_at' => $emailVerified ? now() : null,
                ])->save();

                $teacher->forceFill(['user_id' => $user->id])->save();
                $teacher->setRelation('user', $user);
                $this->userRoleService->syncTeacherRoles($teacher);

                return [
                    'name' => $teacher->name,
                    'email' => $user->email,
                    'password' => $password,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{name:string,email:string,password:string}>
     */
    protected function migrateStudents(bool $emailVerified): array
    {
        return Student::query()
            ->whereNull('user_id')
            ->orderBy('name')
            ->get()
            ->map(function (Student $student) use ($emailVerified): array {
                $password = $this->defaultStudentPassword($student);
                $user = User::query()->create([
                    'name' => $student->name,
                    'email' => $this->uniqueGeneratedEmail('siswa', $student),
                    'password' => $password,
                    'roles' => [UserRole::SISWA->value],
                ]);
                $user->forceFill([
                    'email_verified_at' => $emailVerified ? now() : null,
                ])->save();

                $student->forceFill(['user_id' => $user->id])->save();
                $student->setRelation('user', $user);
                $this->userRoleService->syncStudentRole($student);

                return [
                    'name' => $student->name,
                    'email' => $user->email,
                    'password' => $password,
                ];
            })
            ->all();
    }

    protected function uniqueGeneratedEmail(string $prefix, Model $profile): string
    {
        $baseIdentifier = collect([
            $profile->getAttribute('nip'),
            $profile->getAttribute('nisn'),
            $profile->getAttribute('nik'),
            $profile->getAttribute('name'),
            $profile->getKey(),
        ])
            ->filter(static fn(mixed $value): bool => filled($value))
            ->first();

        $local = Str::of($prefix . '.' . $baseIdentifier)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->toString();

        if ($local === '' || $local === $prefix) {
            $local = "{$prefix}.{$profile->getKey()}";
        }

        $domain = $this->generatedEmailDomain();
        $email = "{$local}@{$domain}";
        $counter = 2;

        while (User::query()->where('email', $email)->exists()) {
            $email = "{$local}.{$counter}@{$domain}";
            $counter++;
        }

        return $email;
    }

    protected function generatedEmailDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return filled($host) ? (string) $host : 'sarunis.local';
    }

    protected function defaultStudentPassword(Student $student): string
    {
        if ($student->birth_date !== null) {
            return $student->birth_date->format('dmY');
        }

        return (string) ($student->nisn ?? $student->nik ?? ('Siswa' . $student->id));
    }

    protected function defaultTeacherPassword(Teacher $teacher): string
    {
        $identifier = (string) ($teacher->nip ?? $teacher->nik ?? $teacher->id);
        $password = 'Guru' . preg_replace('/\D+/', '', $identifier);

        return strlen($password) >= 8 ? $password : 'Guru' . str_pad((string) $teacher->id, 4, '0', STR_PAD_LEFT);
    }
}
