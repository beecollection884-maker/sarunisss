<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\AppSetting;
use App\Models\AuthVerificationCode;
use App\Models\AcademicCalendar;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuthRecoveryAndExtraModulesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_password_recovery_sends_code_verifies_code_and_resets_password(): void
    {
        Mail::fake();

        $this->post('/auth/verifikasi-email', [
            'portal' => 'admin',
            'email' => 'admin@sarunis.test',
        ])->assertRedirect('/auth/verifikasi-email?portal=admin&email=admin%40sarunis.test');

        $this->assertDatabaseHas('auth_verification_codes', [
            'email' => 'admin@sarunis.test',
            'portal' => 'admin',
            'purpose' => 'password_reset',
        ]);

        AuthVerificationCode::query()->where('email', 'admin@sarunis.test')->delete();
        $token = 'valid-token';
        AuthVerificationCode::query()->create([
            'email' => 'admin@sarunis.test',
            'portal' => 'admin',
            'purpose' => 'password_reset',
            'code_hash' => Hash::make($token),
            'reset_token_hash' => Hash::make($token),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->post('/auth/lupa-kata-sandi', [
            'portal' => 'admin',
            'email' => 'admin@sarunis.test',
            'token' => $token,
            'password' => 'Passwordbaru1',
            'password_confirmation' => 'Passwordbaru1',
        ])->assertRedirect('/auth?portal=admin&email=admin%40sarunis.test&reset=success');

        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->assertTrue(Hash::check('Passwordbaru1', $admin->password));
    }

    public function test_api_password_recovery_flow_uses_email_code_and_reset_token(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => ' ADMIN@SARUNIS.TEST ',
        ])->assertOk()
            ->assertJsonPath('data.email', 'admin@sarunis.test');

        $this->assertDatabaseHas('auth_verification_codes', [
            'email' => 'admin@sarunis.test',
            'portal' => 'global',
            'purpose' => 'password_reset',
        ]);

        AuthVerificationCode::query()
            ->where('email', 'admin@sarunis.test')
            ->where('purpose', 'password_reset')
            ->latest()
            ->firstOrFail()
            ->forceFill([
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(15),
            ])->save();

        $verifyResponse = $this->postJson('/api/v1/auth/verify-code', [
            'email' => 'admin@sarunis.test',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('message', 'Kode berhasil diverifikasi.');

        $token = $verifyResponse->json('data.reset_token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'admin@sarunis.test',
            'token' => $token,
            'password' => 'Passwordbaru1',
            'password_confirmation' => 'Passwordbaru1',
        ])->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');

        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->assertTrue(Hash::check('Passwordbaru1', $admin->password));
        $this->assertDatabaseMissing('auth_verification_codes', [
            'email' => 'admin@sarunis.test',
            'purpose' => 'password_reset',
        ]);
    }

    public function test_admin_can_manage_users_settings_and_student_notes(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $student = Student::query()->whereNull('user_id')->firstOrFail();
        $teacher = Teacher::query()->whereNull('user_id')->firstOrFail();
        $this->actingAs($admin);

        $userResponse = $this->postJson('/admin/pengguna', [
            'name' => 'Operator Sekolah',
            'email' => 'operator@sarunis.test',
            'password' => 'password123',
            'roles' => ['admin'],
            'email_verified' => true,
            'teacher_id' => $teacher->id,
        ])->assertCreated();

        $userId = $userResponse->json('data.id');
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'user_id' => $userId,
        ]);

        $this->putJson("/admin/pengguna/{$userId}", [
            'name' => 'Operator Diperbarui',
            'email' => 'operator@sarunis.test',
            'roles' => [],
            'email_verified' => false,
            'student_id' => $student->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Operator Diperbarui');
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'user_id' => $userId,
        ]);

        $settingResponse = $this->postJson('/admin/setting', [
            'key' => 'school_motto',
            'label' => 'Motto Sekolah',
            'value' => 'Belajar dan Bertumbuh',
            'type' => 'text',
        ])->assertCreated();

        $setting = AppSetting::query()->findOrFail($settingResponse->json('data.id'));

        $this->putJson("/admin/setting/{$setting->id}", [
            'key' => 'school_motto',
            'label' => 'Motto Sekolah',
            'value' => 'Belajar, Bertumbuh, Berbagi',
            'type' => 'textarea',
            'description' => 'Tampil di profil sekolah.',
        ])->assertOk()
            ->assertJsonPath('data.type', 'textarea');

        $noteResponse = $this->postJson('/admin/catatan', [
            'student_id' => $student->id,
            'title' => 'Perlu Pendampingan',
            'category' => 'akademik',
            'note' => 'Perlu latihan tambahan matematika.',
            'follow_up_at' => now()->addWeek()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Perlu Pendampingan');

        $note = StudentNote::query()->findOrFail($noteResponse->json('data.id'));

        $this->putJson("/admin/catatan/{$note->id}", [
            'student_id' => $student->id,
            'title' => 'Pendampingan berjalan',
            'category' => 'akademik',
            'note' => 'Sudah diberi jadwal latihan.',
            'resolved_at' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.title', 'Pendampingan berjalan');
    }

    public function test_admin_can_migrate_students_and_teachers_to_users(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $student = Student::query()->whereNull('user_id')->firstOrFail();
        $teacher = Teacher::query()->whereNull('user_id')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson('/admin/pengguna/migrasi-profil', [
                'source' => 'all',
                'email_verified' => true,
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('data.teachers_created'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.students_created'));

        $student->refresh();
        $teacher->refresh();

        $this->assertNotNull($student->user_id);
        $this->assertNotNull($teacher->user_id);
        $this->assertTrue($student->user->hasRole('siswa'));
        $this->assertStringStartsWith('siswa.', $student->user->email);
        $this->assertStringStartsWith('guru.', $teacher->user->email);

        if ($student->birth_date !== null) {
            $this->assertTrue(Hash::check($student->birth_date->format('dmY'), $student->user->password));
        }

        $this->actingAs($admin)
            ->postJson('/admin/pengguna/migrasi-profil', [
                'source' => 'all',
                'email_verified' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.total_created', 0);
    }

    public function test_admin_can_import_students_teachers_and_export_csv_datasets(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $schoolClass = SchoolClass::query()->firstOrFail();
        $this->actingAs($admin);

        $studentCsv = implode("\n", [
            'nik,nisn,name,gender,birth_date,phone,address,school_class_id',
            '90001,5000000001,Siswa Import,L,2010-02-12,081234567890,Jl. Import,'.$schoolClass->id,
        ]);

        $this->postJson('/admin/import/siswa', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $studentCsv),
        ])->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('students', [
            'nik' => '90001',
            'name' => 'Siswa Import',
            'school_class_id' => $schoolClass->id,
        ]);

        $teacherCsv = implode("\n", [
            'nip,nik,name,birth_place,birth_date,gender,religion,employment_status,position,join_date,last_education,major,university,phone,address',
            '990001,330000000001,Guru Import,Bandung,1987-04-10,P,Islam,GTY,Guru,2020-01-10,S1,Pendidikan,Universitas Contoh,081234567891,Jl. Guru Import',
        ]);

        $this->postJson('/admin/import/guru', [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $teacherCsv),
        ])->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('teachers', [
            'nip' => '990001',
            'name' => 'Guru Import',
        ]);

        foreach (['siswa', 'guru', 'kelas', 'absensi', 'catatan-siswa'] as $dataset) {
            $this->get("/admin/export/{$dataset}")
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }

        $this->get('/admin/import-template/siswa')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_academic_calendar_is_scoped_by_academic_year_and_semester(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        AcademicCalendar::query()->delete();
        AppSetting::query()->updateOrCreate(
            ['key' => 'active_semester'],
            ['label' => 'Semester Aktif', 'value' => 'ganjil', 'type' => 'select', 'description' => 'Semester aktif untuk test.'],
        );
        $this->actingAs($admin);

        $response = $this->postJson('/admin/kalender-akademik-data', [
            'academic_year' => '2025/2026',
            'semester' => 'ganjil',
            'title' => 'Penilaian Tengah Semester',
            'category' => 'Ujian',
            'type' => 'ujian',
            'start_date' => '2026-05-25',
            'end_date' => '2026-05-29',
            'description' => 'PTS semester ganjil.',
            'is_holiday' => false,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Penilaian Tengah Semester')
            ->assertJsonPath('data.academic_year', '2025/2026')
            ->assertJsonPath('data.semester', 'ganjil');

        AcademicCalendar::query()->create([
            'academic_year' => '2025/2026',
            'semester' => 'genap',
            'title' => 'Agenda Semester Genap',
            'category' => 'Kegiatan',
            'type' => 'event_sekolah',
            'start_date' => '2026-05-25',
            'end_date' => '2026-05-26',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->get('/admin/kalender-akademik')
            ->assertOk()
            ->assertSee('Kalender Akademik')
            ->assertSee('Penilaian Tengah Semester')
            ->assertDontSee('Agenda Semester Genap');

        $this->getJson('/guru-mapel/kalender-akademik')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Penilaian Tengah Semester');

        $calendar = AcademicCalendar::query()->findOrFail($response->json('data.id'));
        $this->putJson("/admin/kalender-akademik-data/{$calendar->id}", [
            'academic_year' => '2025/2026',
            'semester' => 'ganjil',
            'title' => 'PTS Diperbarui',
            'category' => 'Ujian',
            'type' => 'ujian',
            'start_date' => '2026-05-25',
            'end_date' => '2026-05-30',
            'is_holiday' => true,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.title', 'PTS Diperbarui')
            ->assertJsonPath('data.is_holiday', true);

        $this->deleteJson("/admin/kalender-akademik-data/{$calendar->id}")
            ->assertOk();

        $this->assertDatabaseMissing('academic_calendars', [
            'id' => $calendar->id,
        ]);
    }

    public function test_attendance_is_blocked_on_holiday_and_locked_semester(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $waliUser = User::query()->where('email', 'walikelas@sarunis.test')->firstOrFail();
        $schoolClass = SchoolClass::query()->where('name', 'X IPA 1')->firstOrFail();
        $students = Student::query()->where('school_class_id', $schoolClass->id)->orderBy('id')->take(3)->get();
        AppSetting::query()->updateOrCreate(
            ['key' => 'active_semester'],
            ['label' => 'Semester Aktif', 'value' => 'ganjil', 'type' => 'select', 'description' => 'Semester aktif untuk test.'],
        );

        $holiday = AcademicCalendar::query()->create([
            'academic_year' => '2025/2026',
            'semester' => 'ganjil',
            'title' => 'Libur Sekolah',
            'category' => 'Libur',
            'type' => 'libur_sekolah',
            'start_date' => '2026-03-20',
            'end_date' => '2026-03-20',
            'is_holiday' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($waliUser);

        $this->getJson('/walikelas/status-absensi?date=2026-03-20')
            ->assertOk()
            ->assertJsonPath('data.allowed', false);

        $this->postJson('/walikelas/absensi-kelas', [
            'school_class_id' => $schoolClass->id,
            'attendance_date' => '2026-03-20',
            'attendances' => $students->map(fn (Student $student): array => [
                'student_id' => $student->id,
                'status' => AttendanceStatus::HADIR->value,
            ])->all(),
        ])->assertStatus(422);

        $holiday->delete();
        $this->actingAs($admin);
        $this->postJson('/admin/semester-lock', [
            'academic_year' => '2025/2026',
            'semester' => 'ganjil',
            'notes' => 'Tutup semester untuk rapor.',
        ])->assertOk()
            ->assertJsonPath('data.academic_year', '2025/2026');

        $this->actingAs($waliUser);
        $this->postJson('/walikelas/absensi-kelas', [
            'school_class_id' => $schoolClass->id,
            'attendance_date' => '2026-03-20',
            'attendances' => $students->map(fn (Student $student): array => [
                'student_id' => $student->id,
                'status' => AttendanceStatus::HADIR->value,
            ])->all(),
        ])->assertStatus(422);
    }

    public function test_walikelas_can_manage_notes_only_for_homeroom_students(): void
    {
        $waliUser = User::query()->where('email', 'walikelas@sarunis.test')->firstOrFail();
        $waliClass = SchoolClass::query()->whereHas('homeroomTeacher', fn ($query) => $query->where('user_id', $waliUser->id))->firstOrFail();
        $ownStudent = $waliClass->students()->firstOrFail();
        $otherStudent = Student::query()->where('school_class_id', '!=', $waliClass->id)->firstOrFail();

        $this->actingAs($waliUser);

        $this->get('/walikelas/catatan-siswa')
            ->assertOk()
            ->assertSee('Catatan Siswa', false);

        $noteResponse = $this->postJson('/walikelas/catatan', [
            'student_id' => $ownStudent->id,
            'title' => 'Konsultasi Orang Tua',
            'category' => 'perwalian',
            'note' => 'Orang tua akan dihubungi pekan ini.',
        ])->assertCreated();

        $note = StudentNote::query()->findOrFail($noteResponse->json('data.id'));
        $this->assertSame($waliUser->teacherProfile?->id, $note->teacher_id);

        $this->postJson('/walikelas/catatan', [
            'student_id' => $otherStudent->id,
            'title' => 'Bukan siswa perwalian',
            'category' => 'perwalian',
            'note' => 'Tidak boleh tersimpan.',
        ])->assertForbidden();
    }

    public function test_security_blocks_weak_recovery_password_and_last_admin_removal(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();

        AuthVerificationCode::query()->create([
            'email' => 'admin@sarunis.test',
            'portal' => 'admin',
            'purpose' => 'password_reset',
            'code_hash' => Hash::make('12345'),
            'reset_token_hash' => Hash::make('valid-token'),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->post('/auth/lupa-kata-sandi', [
            'portal' => 'admin',
            'email' => 'admin@sarunis.test',
            'token' => 'valid-token',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin);

        $this->putJson("/admin/pengguna/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [],
            'email_verified' => true,
        ])->assertStatus(422);

        $otherAdmin = User::query()->create([
            'name' => 'Admin Cadangan',
            'email' => 'admin.cadangan@sarunis.test',
            'password' => 'Password123',
            'roles' => ['admin'],
            'email_verified_at' => now(),
        ]);

        $this->deleteJson("/admin/pengguna/{$otherAdmin->id}")
            ->assertOk();

        $this->deleteJson("/admin/pengguna/{$admin->id}")
            ->assertStatus(422);
    }
}

