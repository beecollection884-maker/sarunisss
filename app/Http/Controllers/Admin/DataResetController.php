<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\AppSettingService;
use App\Services\UserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DataResetController extends Controller
{
    public function __construct(
        protected AppSettingService $appSettingService,
        protected UserRoleService $userRoleService,
    ) {}

    /**
     * Defines the table groups that can be reset.
     * Order within each group matters for FK-safe deletion (children first).
     *
     * @return array<string, array{label: string, description: string, icon: string, tables: string[], danger_level: string}>
     */
    protected function tableGroups(): array
    {
        return [
            'absensi_kelas' => [
                'label' => 'Absensi Kelas',
                'description' => 'Data absensi harian kelas (class_attendances).',
                'icon' => 'clipboard',
                'tables' => ['class_attendances'],
                'danger_level' => 'high',
            ],
            'absensi_mapel' => [
                'label' => 'Absensi Mapel',
                'description' => 'Data absensi per mata pelajaran (subject_attendances).',
                'icon' => 'clipboard',
                'tables' => ['subject_attendances'],
                'danger_level' => 'high',
            ],
            'absensi_offline' => [
                'label' => 'Absensi Offline',
                'description' => 'Data absensi dari perangkat offline.',
                'icon' => 'wifi-off',
                'tables' => ['offline_attendances'],
                'danger_level' => 'medium',
            ],
            'catatan_siswa' => [
                'label' => 'Catatan Siswa',
                'description' => 'Catatan perilaku/akademik oleh wali kelas.',
                'icon' => 'file-text',
                'tables' => ['student_notes'],
                'danger_level' => 'medium',
            ],
            'pelanggaran' => [
                'label' => 'Pelanggaran Siswa',
                'description' => 'Data pelanggaran dan poin siswa.',
                'icon' => 'alert-triangle',
                'tables' => ['student_violations'],
                'danger_level' => 'medium',
            ],
            'jadwal_ajar' => [
                'label' => 'Jadwal Ajar',
                'description' => 'Jadwal mengajar dan riwayat generate jadwal.',
                'icon' => 'calendar',
                'tables' => ['subject_attendances', 'teaching_assignments', 'schedule_generations'],
                'danger_level' => 'high',
            ],
            'mata_pelajaran' => [
                'label' => 'Mata Pelajaran',
                'description' => 'Data mapel beserta relasi guru-mapel dan kelas-mapel.',
                'icon' => 'book-open',
                'tables' => ['subject_attendances', 'teaching_assignments', 'school_class_subject', 'subject_teacher', 'subjects'],
                'danger_level' => 'critical',
            ],
            'data_siswa' => [
                'label' => 'Data Siswa',
                'description' => 'Seluruh data siswa termasuk detail, absensi, dan catatan terkait.',
                'icon' => 'graduation-cap',
                'tables' => ['class_attendances', 'subject_attendances', 'student_notes', 'student_violations', 'student_details', 'students'],
                'danger_level' => 'critical',
            ],
            'data_guru' => [
                'label' => 'Data Guru',
                'description' => 'Seluruh data guru termasuk jadwal ajar terkait.',
                'icon' => 'users',
                'tables' => ['subject_attendances', 'teaching_assignments', 'subject_teacher', 'school_classes', 'teachers'],
                'danger_level' => 'critical',
            ],
            'data_kelas' => [
                'label' => 'Data Kelas',
                'description' => 'Seluruh data kelas, plotting siswa dan mapel kelas.',
                'icon' => 'layout',
                'tables' => ['class_attendances', 'teaching_assignments', 'school_class_subject', 'students', 'school_classes'],
                'danger_level' => 'critical',
            ],
            'kalender_akademik' => [
                'label' => 'Kalender Akademik',
                'description' => 'Event kalender akademik (libur, ujian, dll).',
                'icon' => 'calendar-days',
                'tables' => ['academic_calendars'],
                'danger_level' => 'low',
            ],
            'pengumuman' => [
                'label' => 'Pengumuman',
                'description' => 'Data pengumuman sekolah.',
                'icon' => 'megaphone',
                'tables' => ['announcements'],
                'danger_level' => 'low',
            ],
            'semester_lock' => [
                'label' => 'Kunci Semester',
                'description' => 'Status kunci semester.',
                'icon' => 'lock',
                'tables' => ['semester_locks'],
                'danger_level' => 'low',
            ],
        ];
    }

    /**
     * GET /admin/data-reset
     * Returns the list of resetable table groups with row counts.
     */
    public function index(): JsonResponse
    {
        $this->appSettingService->ensureDefaults();

        $groups = [];

        foreach ($this->tableGroups() as $key => $group) {
            $tables = [];
            foreach (array_unique($group['tables']) as $table) {
                if (Schema::hasTable($table)) {
                    $tables[] = [
                        'name' => $table,
                        'row_count' => DB::table($table)->count(),
                    ];
                }
            }

            $totalRows = collect($tables)->sum('row_count');

            $groups[] = [
                'key' => $key,
                'label' => $group['label'],
                'description' => $group['description'],
                'icon' => $group['icon'],
                'danger_level' => $group['danger_level'],
                'tables' => $tables,
                'total_rows' => $totalRows,
            ];
        }

        return response()->json([
            'groups' => $groups,
            'settings' => [
                'attendance_test_mode' => AppSetting::query()->where('key', 'attendance_test_mode')->first(),
            ],
            'protected_tables' => ['users', 'app_settings', 'migrations', 'cache', 'sessions', 'jobs', 'failed_jobs', 'personal_access_tokens', 'auth_verification_codes'],
        ]);
    }

    /**
     * @param array<int, string> $tables
     */
    protected function truncateTables(array $tables): void
    {
        if ($tables === []) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $tableList = collect($tables)
                ->map(fn(string $table): string => '"' . str_replace('"', '""', $table) . '"')
                ->implode(', ');

            DB::statement("TRUNCATE TABLE {$tableList} RESTART IDENTITY CASCADE");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');

            try {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                if (Schema::hasTable('sqlite_sequence')) {
                    DB::table('sqlite_sequence')->whereIn('name', $tables)->delete();
                }
            } finally {
                DB::statement('PRAGMA foreign_keys = ON;');
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                foreach ($tables as $table) {
                    DB::table($table)->truncate();
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
    /**
     * POST /admin/data-reset
     * Validates password, then truncates selected table groups.
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'confirmation_text' => ['required', 'string', 'in:HAPUS DATA'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['string'],
        ], [
            'password.required' => 'Password wajib diisi untuk konfirmasi.',
            'confirmation_text.in' => 'Teks konfirmasi harus "HAPUS DATA".',
            'groups.required' => 'Pilih minimal satu kelompok data.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Re-authenticate with password
        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Password salah. Penghapusan dibatalkan.',
                'errors' => ['password' => ['Password tidak sesuai.']],
            ], 422);
        }

        $allGroups = $this->tableGroups();
        $selectedKeys = $request->input('groups');
        $invalidKeys = array_diff($selectedKeys, array_keys($allGroups));

        if (! empty($invalidKeys)) {
            return response()->json([
                'message' => 'Kelompok data tidak valid: ' . implode(', ', $invalidKeys),
            ], 422);
        }

        // Collect all unique tables from selected groups (preserving FK order)
        $allTables = [];
        foreach ($selectedKeys as $key) {
            foreach ($allGroups[$key]['tables'] as $table) {
                if (! in_array($table, $allTables, true) && Schema::hasTable($table)) {
                    $allTables[] = $table;
                }
            }
        }

        // Count rows before deletion for the summary
        $summary = [];
        foreach ($allTables as $table) {
            $summary[$table] = DB::table($table)->count();
        }

        try {
            $this->truncateTables($allTables);

            if (in_array('data_guru', $selectedKeys, true)) {
                $this->detachOrphanedTeacherRoles();
            }

            if (in_array('data_siswa', $selectedKeys, true)) {
                $this->detachOrphanedStudentRoles();
                $this->detachOrphanedParentRoles();
            }

            if (in_array('data_guru', $selectedKeys, true) || in_array('data_siswa', $selectedKeys, true)) {
                $this->deleteUsersWithoutRoles();
                $this->deleteDuplicateImportedUsers();
            }
        } catch (\Throwable $e) {
            Log::error('Data reset failed', [
                'user_id' => $user->id,
                'groups' => $selectedKeys,
                'tables' => $allTables,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus data. Silakan coba lagi.',
            ], 500);
        }

        // Log the action for audit
        Log::warning('DATA RESET executed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'groups' => $selectedKeys,
            'tables_truncated' => $allTables,
            'rows_deleted' => $summary,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Invalidate caches
        cache()->flush();

        $totalDeleted = array_sum($summary);

        return response()->json([
            'message' => "Berhasil menghapus {$totalDeleted} data dari " . count($allTables) . ' tabel.',
            'summary' => collect($summary)->map(fn(int $count, string $table) => [
                'table' => $table,
                'rows_deleted' => $count,
            ])->values()->all(),
            'total_deleted' => $totalDeleted,
        ]);
    }

    protected function detachOrphanedTeacherRoles(): void
    {
        User::query()
            ->whereJsonContains('roles', UserRole::GURU_MAPEL->value)
            ->doesntHave('teacherProfile')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->userRoleService->detachTeacherRoles($user);
                }
            });
    }

    protected function detachOrphanedStudentRoles(): void
    {
        User::query()
            ->whereJsonContains('roles', UserRole::SISWA->value)
            ->doesntHave('studentProfile')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->userRoleService->detachStudentRole($user);
                }
            });
    }

    protected function detachOrphanedParentRoles(): void
    {
        User::query()
            ->whereJsonContains('roles', UserRole::ORANG_TUA->value)
            ->doesntHave('parentStudents')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->userRoleService->removeRoles($user, [UserRole::ORANG_TUA]);
                }
            });
    }

    protected function isProtectedUser(User $user): bool
    {
        $adminEmails = array_filter([
            env('INITIAL_ADMIN_EMAIL'),
            env('ADMIN_EMAIL'),
        ]);

        return $user->hasRole(UserRole::ADMIN)
            || in_array($user->email, $adminEmails, true);
    }

    protected function deleteUsersWithoutRoles(): void
    {
        User::query()
            ->where(function ($query): void {
                $query->whereNull('roles')
                    ->orWhereJsonLength('roles', 0);
            })
            ->doesntHave('teacherProfile')
            ->doesntHave('studentProfile')
            ->doesntHave('parentStudents')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    if ($this->isProtectedUser($user)) {
                        continue;
                    }

                    $user->delete();
                }
            });
    }

    protected function deleteDuplicateImportedUsers(): void
    {
        $domain = $this->importedEmailDomain();
        $parentEmailDomain = $this->importedParentEmailDomain();

        User::query()
            ->where(function ($query) use ($domain, $parentEmailDomain): void {
                $query->where(function ($subQuery) use ($domain): void {
                    $subQuery->where('email', 'like', 'guru.%@' . $domain)
                        ->whereJsonContains('roles', UserRole::GURU_MAPEL->value)
                        ->doesntHave('teacherProfile');
                })->orWhere(function ($subQuery) use ($domain): void {
                    $subQuery->where('email', 'like', 'siswa.%@' . $domain)
                        ->whereJsonContains('roles', UserRole::SISWA->value)
                        ->doesntHave('studentProfile');
                })->orWhere(function ($subQuery) use ($parentEmailDomain): void {
                    $subQuery->where('email', 'like', 'orangtua.%@' . $parentEmailDomain)
                        ->whereJsonContains('roles', UserRole::ORANG_TUA->value)
                        ->doesntHave('parentStudents');
                });
            })
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    if ($this->isProtectedUser($user)) {
                        continue;
                    }

                    $user->delete();
                }
            });
    }

    protected function importedEmailDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return filled($host) ? (string) $host : 'sarunis.local';
    }

    protected function importedParentEmailDomain(): string
    {
        return 'sch.id';
    }
}
