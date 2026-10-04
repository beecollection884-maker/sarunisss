<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\AppSetting;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthPortalEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function tearDown(): void
    {
        foreach (
            [
                'admin@sarunis.test|127.0.0.1',
                'guru.mapel@sarunis.test|127.0.0.1',
                'walikelas@sarunis.test|127.0.0.1',
                'guru.wali@sarunis.test|127.0.0.1',
                'orangtua@sarunis.test|127.0.0.1',
                'siswa@sarunis.test|127.0.0.1',
                'unknown@sarunis.test|127.0.0.1',
            ] as $rateLimitKey
        ) {
            RateLimiter::clear($rateLimitKey);
        }

        parent::tearDown();
    }

    public function test_generic_login_returns_available_portals_and_me_profile(): void
    {
        $response = $this->loginByPath('/api/v1/auth/login', 'orangtua@sarunis.test');

        $response->assertOk()
            ->assertJsonPath('data.active_portal', 'orang-tua')
            ->assertJsonPath('data.redirect_to', '/orang-tua/dashboard')
            ->assertJsonFragment(['key' => 'orang-tua']);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'orangtua@sarunis.test')
            ->assertJsonPath('data.active_portal', 'orang-tua');
    }

    public function test_generic_login_redirects_each_showcase_role_to_its_default_dashboard(): void
    {
        $accounts = [
            'admin@sarunis.test' => ['admin', '/admin/dashboard'],
            'guru.mapel@sarunis.test' => ['walikelas', '/walikelas/dashboard'],
            'walikelas@sarunis.test' => ['walikelas', '/walikelas/dashboard'],
            'orangtua@sarunis.test' => ['orang-tua', '/orang-tua/dashboard'],
            'siswa@sarunis.test' => ['siswa', '/siswa/dashboard'],
        ];

        foreach ($accounts as $email => [$portal, $dashboard]) {
            $this->postJson('/api/v1/auth/logout');
            $this->flushSession();
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            \Illuminate\Support\Facades\Auth::guard('web')->forgetUser();
            \Illuminate\Support\Facades\Auth::forgetUser();
            $this->cookies = [];

            $this->loginByPath('/api/v1/auth/login', $email)
                ->assertOk()
                ->assertJsonPath('data.active_portal', $portal)
                ->assertJsonPath('data.redirect_to', $dashboard);
        }
    }

    public function test_login_with_remember_flag_still_authenticates_correctly(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@sarunis.test',
            'password' => 'password',
            'remember' => true,
        ]);

        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'admin@sarunis.test')
            ->assertJsonPath('data.active_portal', 'admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_portal_login_rejects_wrong_role(): void
    {
        $this->postJson('/api/v1/auth/login/guru-mapel', [
            'email' => 'siswa@sarunis.test',
            'password' => 'password',
        ])->assertForbidden();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_can_manage_master_data_and_plot_class(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $teacherUser = User::query()->create([
            'name' => 'Guru Baru',
            'email' => 'guru.baru@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $studentUser = User::query()->create([
            'name' => 'Siswa Baru',
            'email' => 'siswa.baru@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $teacherResponse = $this->postJson('/api/v1/admin/guru', [
            'user_id' => $teacherUser->id,
            'nip' => '198900000010',
            'name' => 'Guru Baru',
            'is_subject_teacher' => true,
            'phone' => '081299999901',
            'address' => 'Jl. Guru Baru',
        ])->assertCreated();

        $subjectResponse = $this->postJson('/api/v1/admin/mapel', [
            'code' => 'SEJ',
            'name' => 'Sejarah',
            'description' => 'Sejarah Indonesia',
        ])->assertCreated();

        $classResponse = $this->postJson('/api/v1/admin/kelas', [
            'name' => 'X IPS 1',
            'level' => 'X',
            'academic_year' => '2025/2026',
            'description' => 'Kelas IPS baru',
        ])->assertCreated();

        $studentResponse = $this->postJson('/api/v1/admin/siswa', [
            'user_id' => $studentUser->id,
            'nik' => '20001',
            'nisn' => '4000000001',
            'name' => 'Siswa Baru',
            'gender' => 'P',
            'birth_date' => '2010-07-16',
            'phone' => '081399999901',
            'address' => 'Jl. Siswa Baru',
        ])->assertCreated();

        $teacherId = $teacherResponse->json('data.id');
        $subjectId = $subjectResponse->json('data.id');
        $classId = $classResponse->json('data.id');
        $studentId = $studentResponse->json('data.id');

        $this->putJson("/api/v1/admin/kelas/{$classId}/ploting", [
            'homeroom_teacher_id' => $teacherId,
            'student_ids' => [$studentId],
            'subject_ids' => [$subjectId],
        ])->assertOk()
            ->assertJsonPath('data.homeroom_teacher_id', $teacherId);

        $this->assertDatabaseHas('school_class_subject', [
            'school_class_id' => $classId,
            'subject_id' => $subjectId,
        ]);

        $this->postJson('/api/v1/admin/jadwal-ajar', [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'school_class_id' => $classId,
            'academic_year' => '2025/2026',
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '13:30',
            'room' => 'R-201',
        ])->assertCreated();

        $teacherUser->refresh();
        $studentUser->refresh();

        $this->assertContains('guru_mapel', $teacherUser->roles);
        $this->assertContains('siswa', $studentUser->roles);
    }

    public function test_admin_can_download_import_templates_for_each_dataset(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $siswa = $this->get('/api/v1/admin/import/template/siswa')->assertOk()->streamedContent();
        $this->assertStringContainsString('nama', $siswa);
        $this->assertStringContainsString('nama_kelas', $siswa);
        $this->assertStringContainsString('no_hp_orang_tua', $siswa);

        $guru = $this->get('/api/v1/admin/import/template/guru')->assertOk()->streamedContent();
        $this->assertStringContainsString('nama', $guru);
        $this->assertStringContainsString('status_kepegawaian', $guru);
        $this->assertStringContainsString('no_hp', $guru);

        $jadwal = $this->get('/api/v1/admin/import/template/jadwal')->assertOk()->streamedContent();
        $this->assertStringContainsString('nip_guru', $jadwal);
        $this->assertStringContainsString('nama_mapel', $jadwal);
        $this->assertStringContainsString('jam_mulai', $jadwal);

        $catatan = $this->get('/api/v1/admin/import/template/catatan-siswa')->assertOk()->streamedContent();
        $this->assertStringContainsString('nik_siswa', $catatan);
        $this->assertStringContainsString('judul', $catatan);
        $this->assertStringContainsString('catatan', $catatan);
    }
    public function test_admin_can_import_guru_csv_with_excel_sep_preamble(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'sep=,',
            'nip,nik,nama,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,status_kepegawaian,jabatan,tanggal_masuk,pendidikan_terakhir,jurusan,universitas,no_hp,alamat',
            '991123,330000009912,Guru Sep,Bandung,1988-05-01,L,Islam,GTY,Guru,2020-01-01,S1,Pendidikan,Universitas Contoh,081234567892,Jl Import Sep',
        ]);

        $this->postJson('/api/v1/admin/import/guru', [
            'file' => UploadedFile::fake()->createWithContent('guru-sep.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('teachers', [
            'nip' => '991123',
            'name' => 'Guru Sep',
        ]);
    }
    public function test_admin_can_import_guru_with_indonesian_csv_headers(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nip,nik,nama,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,status_kepegawaian,jabatan,tanggal_masuk,pendidikan_terakhir,jurusan,universitas,no_hp,alamat',
            '991122,330000009911,Guru CSV,Bandung,1988-05-01,Laki-laki,Islam,GTY,Guru,2020-01-01,S1,Pendidikan,Universitas Contoh,081234567899,Jl Import',
        ]);

        $this->postJson('/api/v1/admin/import/guru', [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('teachers', [
            'nip' => '991122',
            'name' => 'Guru CSV',
            'gender' => 'L',
            'phone' => '081234567899',
        ]);

        $teacher = Teacher::query()->where('nip', '991122')->with('user')->firstOrFail();
        $this->assertNotNull($teacher->user);
        $this->assertContains('guru_mapel', $teacher->user->roles);
        $this->assertTrue(Hash::check('ipyakin2026', $teacher->user->password));
    }

    public function test_admin_reuses_existing_teacher_user_after_profile_reset(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nip,nik,nama,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,status_kepegawaian,jabatan,tanggal_masuk,pendidikan_terakhir,jurusan,universitas,no_hp,alamat',
            '991123,330000009912,Guru Reuse,Bandung,1988-05-01,L,Islam,GTY,Guru,2020-01-01,S1,Pendidikan,Universitas Contoh,081234567899,Jl Import',
        ]);

        $this->postJson('/api/v1/admin/import/guru', [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $teacher = Teacher::query()->where('nip', '991123')->with('user')->firstOrFail();
        $originalUser = $teacher->user;

        $teacher->delete();

        $this->postJson('/api/v1/admin/import/guru', [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $reimportedTeacher = Teacher::query()->where('nip', '991123')->with('user')->firstOrFail();
        $this->assertSame($originalUser->id, $reimportedTeacher->user_id);
        $this->assertSame($originalUser->email, $reimportedTeacher->user->email);
        $this->assertSame(1, User::query()->where('email', $originalUser->email)->count());
    }

    public function test_data_reset_detaches_orphaned_roles_for_teacher_student_and_parent(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $response = $this->postJson('/api/v1/admin/data-reset', [
            'password' => 'password',
            'confirmation_text' => 'HAPUS DATA',
            'groups' => ['data_guru', 'data_siswa'],
        ]);

        $response->assertOk();

        $this->assertSame(0, User::query()
            ->whereJsonContains('roles', 'guru_mapel')
            ->doesntHave('teacherProfile')
            ->count());

        $this->assertSame(0, User::query()
            ->whereJsonContains('roles', 'siswa')
            ->doesntHave('studentProfile')
            ->count());

        $this->assertSame(0, User::query()
            ->whereJsonContains('roles', 'orang_tua')
            ->doesntHave('parentStudents')
            ->count());

        $this->assertSame(0, User::query()
            ->where(function ($query): void {
                $query->whereNull('roles')
                    ->orWhereJsonLength('roles', 0);
            })
            ->doesntHave('teacherProfile')
            ->doesntHave('studentProfile')
            ->doesntHave('parentStudents')
            ->count());
    }

    public function test_data_reset_deletes_users_without_roles(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $orphanUser = User::query()->create([
            'name' => 'Orphan User',
            'email' => 'orphan.user@example.test',
            'password' => 'secret',
            'roles' => [],
        ]);

        $response = $this->postJson('/api/v1/admin/data-reset', [
            'password' => 'password',
            'confirmation_text' => 'HAPUS DATA',
            'groups' => ['data_guru', 'data_siswa'],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $orphanUser->id,
            'email' => 'orphan.user@example.test',
        ]);
    }

    public function test_data_reset_does_not_remove_admin_users(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();

        $response = $this->postJson('/api/v1/admin/data-reset', [
            'password' => 'password',
            'confirmation_text' => 'HAPUS DATA',
            'groups' => ['data_guru', 'data_siswa'],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin@sarunis.test',
        ]);
        $this->assertTrue(User::query()->where('id', $admin->id)->exists());
    }

    public function test_data_reset_deletes_duplicate_imported_guru_siswa_and_parent_users(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sarunis.local';

        $duplicateGuru = User::query()->create([
            'name' => 'Guru Duplikat',
            'email' => "guru.duplicate@{$domain}",
            'password' => 'secret',
            'roles' => [],
        ]);

        $duplicateSiswa = User::query()->create([
            'name' => 'Siswa Duplikat',
            'email' => "siswa.duplicate@{$domain}",
            'password' => 'secret',
            'roles' => [],
        ]);

        $duplicateParent = User::query()->create([
            'name' => 'Orang Tua Duplikat',
            'email' => 'orangtua.duplicate@sch.id',
            'password' => 'secret',
            'roles' => [],
        ]);

        $response = $this->postJson('/api/v1/admin/data-reset', [
            'password' => 'password',
            'confirmation_text' => 'HAPUS DATA',
            'groups' => ['data_guru', 'data_siswa'],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $duplicateGuru->id,
            'email' => "guru.duplicate@{$domain}",
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $duplicateSiswa->id,
            'email' => "siswa.duplicate@{$domain}",
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $duplicateParent->id,
            'email' => 'orangtua.duplicate@sch.id',
        ]);
    }

    public function test_importing_siswa_reuses_existing_parent_account_by_canonical_email(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $existingParent = User::query()->create([
            'name' => 'Ibu CSV',
            'email' => 'orangtua.ibu.csv.081234567801@sch.id',
            'password' => 'secret',
            'roles' => [],
        ]);

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,nama_kelas,agama,tempat_lahir,nama_ayah,nama_ibu,no_hp_orang_tua,sekolah_asal',
            '880003,1234567892,Siswa Reuse,Laki-laki,2011-02-03,081234567802,Jl Reuse,X IPA 1,Islam,Bandung,Bapak CSV,Ibu CSV,081234567801,SD Contoh',
        ]);

        $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $student = Student::query()->where('nik', '880003')->with('parentUser')->firstOrFail();

        $this->assertSame($existingParent->id, $student->parentUser->id);
        $this->assertDatabaseHas('users', [
            'id' => $existingParent->id,
            'email' => 'orangtua.ibu.csv.081234567801@sch.id',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'orangtua.ibu.csv.081234567801.2@sch.id',
        ]);
    }

    public function test_importing_siswa_requires_parent_name_and_phone_for_parent_canonical_email(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,nama_kelas,agama,tempat_lahir,no_hp_orang_tua,sekolah_asal',
            '880004,1234567893,Siswa Invalid,Laki-laki,2011-02-03,081234567803,Jl Invalid,X IPA 1,Islam,Bandung,,SD Contoh',
        ]);

        $response = $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('errors.0.messages.2', 'Nomor HP orang tua wajib diisi untuk membentuk email parent canonical.');

        $this->assertDatabaseMissing('students', [
            'nik' => '880004',
            'name' => 'Siswa Invalid',
        ]);
    }

    public function test_admin_can_import_siswa_with_indonesian_csv_headers(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,nama_kelas,agama,tempat_lahir,nama_ayah,nama_ibu,no_hp_orang_tua,sekolah_asal',
            '880001,1234567890,Siswa CSV,Laki-laki,2011-02-03,081234567800,Jl Siswa Import,X IPA 1,Islam,Bandung,Bapak CSV,Ibu CSV,081234567801,SD Contoh',
        ]);

        $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $classId = SchoolClass::query()->where('name', 'X IPA 1')->value('id');

        $this->assertDatabaseHas('students', [
            'nik' => '880001',
            'nisn' => '1234567890',
            'name' => 'Siswa CSV',
            'gender' => 'L',
            'school_class_id' => $classId,
        ]);

        $student = Student::query()->where('nik', '880001')->with(['user', 'parentUser'])->firstOrFail();
        $this->assertNotNull($student->user);
        $this->assertNotNull($student->parentUser);
        $this->assertContains('siswa', $student->user->roles);
        $this->assertContains('orang_tua', $student->parentUser->roles);
        $this->assertTrue(Hash::check('ipyakin2026', $student->user->password));
        $this->assertTrue(Hash::check('ipyakin2026', $student->parentUser->password));
    }

    public function test_admin_can_import_siswa_with_new_class_name(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,nama_kelas,agama,tempat_lahir,nama_ayah,nama_ibu,no_hp_orang_tua,sekolah_asal',
            '880002,1234567891,Siswa Kelas Baru,Laki-laki,2011-02-03,081234567801,Jl Siswa Import,Kelas Baru 10A,Islam,Bandung,Bapak CSV,Ibu CSV,081234567802,SD Contoh',
        ]);

        $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('students', [
            'nik' => '880002',
            'name' => 'Siswa Kelas Baru',
        ]);
        $this->assertDatabaseHas('school_classes', [
            'name' => 'Kelas Baru 10A',
        ]);
    }

    public function test_admin_import_siswa_requires_class_name_or_id(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,agama,tempat_lahir,nama_ayah,nama_ibu,no_hp_orang_tua,sekolah_asal',
            '880003,1234567892,Siswa Tanpa Kelas,Laki-laki,2011-02-03,081234567802,Jl Siswa Import,Islam,Bandung,Bapak CSV,Ibu CSV,081234567803,SD Contoh',
        ]);

        $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertStatus(422)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('errors.0.messages.0', 'Nama Kelas atau ID Kelas wajib diisi agar siswa dapat ditempatkan di kelas.');

        $this->assertDatabaseMissing('students', [
            'nik' => '880003',
            'name' => 'Siswa Tanpa Kelas',
        ]);
    }

    public function test_admin_can_import_siswa_with_class_name_case_insensitive_match(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik,nisn,nama,jenis_kelamin,tanggal_lahir,no_hp,alamat,nama_kelas,agama,tempat_lahir,nama_ayah,nama_ibu,no_hp_orang_tua,sekolah_asal',
            '880004,1234567893,Siswa CaseInsensitive,Laki-laki,2011-02-03,081234567803,Jl Siswa Import, x ipa 1 ,Islam,Bandung,Bapak CSV,Ibu CSV,081234567804,SD Contoh',
        ]);

        $this->postJson('/api/v1/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('failed', 0);

        $classId = SchoolClass::query()->where('name', 'X IPA 1')->value('id');
        $this->assertDatabaseHas('students', [
            'nik' => '880004',
            'school_class_id' => $classId,
        ]);
    }

    public function test_admin_can_import_jadwal_with_common_csv_headers(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nip,kode_mapel,kelas,hari,mulai,selesai,ruang',
            '198801010001,MAT,X IPA 1,Minggu,15:00,16:00,R-Import',
        ]);

        $this->postJson('/api/v1/admin/import/jadwal', [
            'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 0);

        $classId = SchoolClass::query()->where('name', 'X IPA 1')->value('id');
        $subjectId = Subject::query()->where('code', 'MAT')->value('id');

        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subjectId,
            'school_class_id' => $classId,
            'day_of_week' => 6,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'room' => 'R-Import',
        ]);
    }

    public function test_schedule_import_creates_missing_subjects_and_skips_existing_rows(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nip_guru,nama_mapel,nama_kelas,hari,jam_mulai,jam_selesai,ruangan',
            '198801010001,Mapel Baru Import,X IPA 1,Minggu,16:15,17:15,R-Baru',
            '198801010001,Mapel Baru Import,X IPA 1,Minggu,16:15,17:15,R-Duplikat',
        ]);

        $this->postJson('/api/v1/admin/import/jadwal', [
            'file' => UploadedFile::fake()->createWithContent('jadwal-baru.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 1);

        $subject = Subject::query()->where('name', 'Mapel Baru Import')->firstOrFail();
        $classId = SchoolClass::query()->where('name', 'X IPA 1')->value('id');

        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subject->id,
            'school_class_id' => $classId,
            'day_of_week' => 6,
            'start_time' => '16:15',
            'end_time' => '17:15',
            'room' => 'R-Baru',
        ]);
    }
    public function test_admin_can_import_student_notes_and_skip_duplicates(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $csv = implode("\n", [
            'nik_siswa,nama_siswa,nip_guru,judul,kategori,catatan,tanggal_tindak_lanjut,tanggal_selesai',
            '10001,Andi Saputra,198802020002,Perlu Pendampingan,akademik,Perlu latihan tambahan matematika,2026-03-25,',
            '10001,Andi Saputra,198802020002,Perlu Pendampingan,akademik,Perlu latihan tambahan matematika,2026-03-25,',
        ]);

        $this->postJson('/api/v1/admin/import/catatan-siswa', [
            'file' => UploadedFile::fake()->createWithContent('catatan-siswa.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('failed', 1);

        $student = Student::query()->where('nik', '10001')->firstOrFail();

        $this->assertDatabaseHas('student_notes', [
            'student_id' => $student->id,
            'title' => 'Perlu Pendampingan',
            'category' => 'akademik',
            'note' => 'Perlu latihan tambahan matematika',
        ]);

        $this->assertSame(1, StudentNote::query()
            ->where('student_id', $student->id)
            ->where('title', 'Perlu Pendampingan')
            ->count());
    }
    public function test_admin_can_fetch_unassigned_students_for_class_plotting(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $this->getJson('/api/v1/admin/siswa/tidak-ada-kelas')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_access_non_admin_portal_features_without_teacher_or_student_profile(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $this->getJson('/api/v1/guru-mapel/jadwal-ajar')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->getJson('/api/v1/guru-mapel/siswa')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->getJson('/api/v1/guru-mapel/rekap-absensi-mapel')
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $this->getJson('/api/v1/walikelas/kelas')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/walikelas/siswa')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->getJson('/api/v1/walikelas/rekap-absensi-kelas')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->getJson('/api/v1/siswa/jadwal-sekolah')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->getJson('/api/v1/siswa/absensi-kelas')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_guru_mapel_can_login_view_schedule_and_record_subject_attendance(): void
    {
        $this->loginByPath('/api/v1/auth/login/guru-mapel', 'guru.mapel@sarunis.test');

        $this->getJson('/api/v1/guru-mapel/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'guru mapel')
            ->assertJsonFragment(['melakukan absensi mapel'])
            ->assertJsonMissing(['crud data siswa']);

        // Check access restriction for Admin endpoint as a subject teacher
        $this->getJson('/api/v1/admin/guru')
            ->assertForbidden();

        $this->getJson('/api/v1/guru-mapel/jadwal-ajar')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/guru-mapel/siswa')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Andi Saputra'])
            ->assertJsonFragment(['name' => 'Fira Azzahra']);

        $assignment = TeachingAssignment::query()
            ->whereHas('teacher.user', fn($query) => $query->where('email', 'guru.mapel@sarunis.test'))
            ->whereHas('schoolClass', fn($query) => $query->where('name', 'X IPA 2'))
            ->firstOrFail();

        $students = Student::query()
            ->where('school_class_id', $assignment->school_class_id)
            ->orderBy('id')
            ->get();

        $targetDate = \Illuminate\Support\Carbon::today();
        for ($i = 0; $i < 7; $i++) {
            $candidate = \Illuminate\Support\Carbon::today()->addDays($i);
            if ($candidate->dayOfWeekIso !== 7 && ($candidate->dayOfWeekIso - 1) === (int) $assignment->day_of_week) {
                $targetDate = $candidate;
                break;
            }
        }
        $attendanceDate = $targetDate->toDateString();

        $this->postJson('/api/v1/guru-mapel/absensi-mapel', [
            'teaching_assignment_id' => $assignment->id,
            'attendance_date' => $attendanceDate,
            'attendances' => [
                ['student_id' => $students[0]->id, 'status' => AttendanceStatus::HADIR->value],
                ['student_id' => $students[1]->id, 'status' => AttendanceStatus::IZIN->value, 'notes' => 'Urusan keluarga'],
                ['student_id' => $students[2]->id, 'status' => AttendanceStatus::HADIR->value],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subject_attendances', [
            'teaching_assignment_id' => $assignment->id,
            'student_id' => $students[1]->id,
            'attendance_date' => $attendanceDate,
            'status' => AttendanceStatus::IZIN->value,
        ]);

        $this->getJson('/api/v1/guru-mapel/rekap-absensi-mapel?teaching_assignment_id=' . $assignment->id . '&attendance_date=' . $attendanceDate)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_reset_selected_data_group(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $this->postJson('/api/v1/admin/data-reset', [
            'password' => 'password',
            'confirmation_text' => 'HAPUS DATA',
            'groups' => ['pengumuman'],
        ])->assertOk()
            ->assertJsonStructure(['message', 'summary', 'total_deleted']);
    }
    public function test_attendance_test_mode_allows_subject_attendance_outside_schedule_day(): void
    {
        $this->loginByPath('/api/v1/auth/login/guru-mapel', 'guru.mapel@sarunis.test');

        $assignment = TeachingAssignment::query()
            ->whereHas('teacher.user', fn($query) => $query->where('email', 'guru.mapel@sarunis.test'))
            ->firstOrFail();

        $badDate = collect(['2026-03-16', '2026-03-17', '2026-03-18', '2026-03-19', '2026-03-20', '2026-03-21'])
            ->first(fn(string $date): bool => ((int) \Carbon\CarbonImmutable::parse($date)->dayOfWeekIso - 1) !== (int) $assignment->day_of_week);

        $students = Student::query()
            ->where('school_class_id', $assignment->school_class_id)
            ->orderBy('id')
            ->take(1)
            ->get();

        $payload = [
            'teaching_assignment_id' => $assignment->id,
            'attendance_date' => $badDate,
            'attendances' => [
                ['student_id' => $students[0]->id, 'status' => AttendanceStatus::HADIR->value],
            ],
        ];

        AppSetting::query()->updateOrCreate(
            ['key' => 'attendance_test_mode'],
            ['label' => 'Mode Test Absensi', 'value' => '0', 'type' => 'boolean', 'description' => 'Mode test absensi.'],
        );

        $this->postJson('/api/v1/guru-mapel/absensi-mapel', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attendance_date');

        AppSetting::query()->where('key', 'attendance_test_mode')->update(['value' => '1']);

        $this->postJson('/api/v1/guru-mapel/absensi-mapel', $payload)
            ->assertOk();
    }
    public function test_walikelas_can_login_view_students_and_record_class_attendance(): void
    {
        $this->loginByPath('/api/v1/auth/login/walikelas', 'walikelas@sarunis.test');

        $this->getJson('/api/v1/walikelas/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'walikelas')
            ->assertJsonFragment(['melakukan absensi harian kelas'])
            ->assertJsonMissing(['crud data siswa']);

        $this->getJson('/api/v1/walikelas/kelas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'X IPA 1']);

        $this->getJson('/api/v1/walikelas/siswa')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Andi Saputra']);

        $schoolClass = SchoolClass::query()->where('name', 'X IPA 1')->firstOrFail();
        $students = Student::query()->where('school_class_id', $schoolClass->id)->orderBy('id')->get();

        $this->postJson('/api/v1/walikelas/absensi-kelas', [
            'school_class_id' => $schoolClass->id,
            'attendance_date' => '2026-03-20',
            'attendances' => [
                ['student_id' => $students[0]->id, 'status' => AttendanceStatus::HADIR->value],
                ['student_id' => $students[1]->id, 'status' => AttendanceStatus::HADIR->value],
                ['student_id' => $students[2]->id, 'status' => AttendanceStatus::SAKIT->value, 'notes' => 'Flu'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('class_attendances', [
            'school_class_id' => $schoolClass->id,
            'student_id' => $students[2]->id,
            'attendance_date' => '2026-03-20',
            'status' => AttendanceStatus::SAKIT->value,
        ]);

        $this->getJson('/api/v1/walikelas/rekap-absensi-kelas?school_class_id=' . $schoolClass->id . '&attendance_date=2026-03-20')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_guru_mapel_can_export_subject_attendance_recap(): void
    {
        $this->loginByPath('/api/v1/auth/login/guru-mapel', 'guru.mapel@sarunis.test');

        $response = $this->get('/api/v1/guru-mapel/absensi/export/csv?date_from=2026-03-01&date_to=2026-03-31');
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Siswa', $response->streamedContent());

        $this->get('/api/v1/guru-mapel/absensi/export/xls?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    }

    public function test_walikelas_can_export_class_attendance_recap(): void
    {
        $this->loginByPath('/api/v1/auth/login/walikelas', 'walikelas@sarunis.test');

        $response = $this->get('/api/v1/walikelas/absensi/export/csv?date_from=2026-03-01&date_to=2026-03-31');
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Siswa', $response->streamedContent());

        $this->get('/api/v1/walikelas/absensi/export/xls?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    }

    public function test_walikelas_student_endpoint_allows_first_period_daily_attendance_class(): void
    {
        $this->loginByPath('/api/v1/auth/login/walikelas', 'guru.mapel@sarunis.test');

        $firstPeriodClass = SchoolClass::query()->where('name', 'X IPA 1')->firstOrFail();
        $unrelatedClass = SchoolClass::query()->where('name', 'XI IPS 1')->firstOrFail();

        $this->getJson('/api/v1/walikelas/kelas')
            ->assertOk()
            ->assertJsonFragment(['name' => 'X IPA 1'])
            ->assertJsonFragment(['name' => 'X IPA 2']);

        $this->getJson('/api/v1/walikelas/siswa?school_class_id=' . $firstPeriodClass->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Andi Saputra']);

        $this->getJson('/api/v1/walikelas/siswa?school_class_id=' . $unrelatedClass->id)
            ->assertForbidden();
    }
    public function test_parent_user_can_access_parent_dashboard_after_portal_login(): void
    {
        $response = $this->loginByPath('/api/v1/auth/login/orang-tua', 'orangtua@sarunis.test');

        $response->assertJsonPath('data.redirect_to', '/orang-tua/dashboard');

        $this->getJson('/api/v1/orang-tua/dashboard')
            ->assertOk()
            ->assertJsonPath('area', 'orang tua');
    }

    public function test_siswa_can_login_view_schedule_attendance_and_logout(): void
    {
        $this->loginByPath('/api/v1/auth/login/siswa', 'siswa@sarunis.test');

        $this->getJson('/api/v1/siswa/jadwal-sekolah')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/siswa/absensi-kelas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', AttendanceStatus::HADIR->value);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->flushSession();
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        \Illuminate\Support\Facades\Auth::guard('web')->forgetUser();
        \Illuminate\Support\Facades\Auth::forgetUser();
        $this->cookies = [];

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'unknown@sarunis.test',
                'password' => 'salah',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@sarunis.test',
            'password' => 'salah',
        ])->assertStatus(429);
    }

    public function test_teacher_role_auto_sync_on_login_after_admin_password_change(): void
    {
        $teacher = Teacher::query()->firstOrFail();
        $user = $teacher->user;
        $this->assertNotNull($user);

        // Simulasikan admin mengganti password dan tidak me-pass roles (roles terhapus di DB)
        $user->forceFill([
            'password' => 'newpassword123',
            'roles' => [],
        ])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'newpassword123',
        ])->assertOk()
          ->assertJsonPath('data.logged_in_user.email', $user->email);

        $user->refresh();
        $this->assertContains('guru_mapel', $user->roles);
    }

    public function test_profile_update_password_without_double_hash(): void
    {
        $user = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();

        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $this->putJson('/api/v1/profile', [
            'email' => $user->email,
            'name' => $user->name,
            'current_password' => 'password',
            'password' => 'newadminpwd123',
            'password_confirmation' => 'newadminpwd123',
        ])->assertOk();

        $user->refresh();

        // Verifikasi password baru dapat digunakan untuk login tanpa double hashing
        $this->postJson('/api/v1/auth/logout');
        $this->flushSession();
        \Illuminate\Support\Facades\Auth::guard('web')->logout();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@sarunis.test',
            'password' => 'newadminpwd123',
        ])->assertOk();
    }

    public function test_admin_can_update_student_profile_with_short_or_empty_nisn(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $student = Student::query()->firstOrFail();
        $student->forceFill(['nisn' => '10001'])->save();

        $this->putJson("/api/v1/admin/siswa/{$student->id}", [
            'nik' => $student->nik,
            'nisn' => $student->nisn,
            'name' => $student->name,
            'address' => 'Jl. Alamat Tambahan H',
        ])->assertOk();

        $student->refresh();
        $this->assertSame('Jl. Alamat Tambahan H', $student->address);
    }

    public function test_unassigned_student_can_access_dashboard_and_pages_without_error(): void
    {
        $unassignedUser = User::create([
            'name' => 'Siswa Tanpa Kelas',
            'email' => 'siswa.tanpakelas@sarunis.test',
            'password' => 'password',
            'roles' => ['siswa'],
        ]);

        $this->actingAs($unassignedUser);

        $this->getJson('/api/v1/siswa/dashboard')
            ->assertOk()
            ->assertJsonPath('isUnassigned', true);

        $this->getJson('/api/v1/siswa/jadwal-sekolah')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/siswa/absensi-kelas')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/siswa/absensi-mapel')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/siswa/catatan')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_admin_user_update_password_preserves_and_relinks_student_profile(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $student = Student::query()->whereNotNull('user_id')->firstOrFail();
        $user = $student->user;

        // Admin updates student user password without student_id in payload
        $this->putJson("/api/v1/admin/pengguna/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles,
            'password' => 'newstudentpwd123',
            'password_confirmation' => 'newstudentpwd123',
        ])->assertOk();

        $student->refresh();
        $this->assertEquals($user->id, $student->user_id);
    }

    public function test_admin_user_update_preserves_teacher_role_even_without_subject_assignment(): void
    {
        $this->loginByPath('/api/v1/auth/login/admin', 'admin@sarunis.test');

        $teacherUser = User::create([
            'name' => 'INTAN MAHARANI, S.Pd',
            'email' => 'guru.19850210120240110021@sarunis.test',
            'password' => 'password',
            'roles' => ['guru_mapel'],
        ]);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '19850210120240110021',
            'name' => 'INTAN MAHARANI, S.Pd',
        ]);

        // Admin updates teacher user password / info
        $response = $this->putJson("/api/v1/admin/pengguna/{$teacherUser->id}", [
            'name' => $teacherUser->name,
            'email' => $teacherUser->email,
            'roles' => ['guru_mapel'],
            'password' => 'newteacherpwd123',
            'password_confirmation' => 'newteacherpwd123',
        ])->assertOk();

        $teacherUser->refresh();
        $this->assertContains('guru_mapel', $teacherUser->roles);
    }

    public function test_teacher_without_subject_assignment_can_login_to_guru_mapel_portal(): void
    {
        $teacherUser = User::create([
            'name' => 'MARZUKI, S.Pd',
            'email' => 'guru.19850170120240110017@sarunis.test',
            'password' => 'password',
            'roles' => ['guru_mapel'],
        ]);

        Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '19850170120240110017',
            'name' => 'MARZUKI, S.Pd',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'guru.19850170120240110017@sarunis.test',
            'password' => 'password',
        ])->assertOk()
          ->assertJsonPath('data.active_portal', 'guru-mapel');
    }

    protected function loginByPath(string $path, string $email): TestResponse
    {
        $response = $this->postJson($path, [
            'email' => $email,
            'password' => 'password',
        ]);

        $sessionCookie = $response->getCookie('laravel_session');

        if ($sessionCookie !== null) {
            $this->withCookie($sessionCookie->getName(), $sessionCookie->getValue());
            $this->withCredentials();
        }

        return $response;
    }
}
