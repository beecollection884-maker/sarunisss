<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCrudValidationAndPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_admin_can_upload_and_remove_teacher_and_student_photo(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $teacherUser = User::query()->create([
            'name' => 'Guru Foto',
            'email' => 'guru.foto@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $studentUser = User::query()->create([
            'name' => 'Siswa Foto',
            'email' => 'siswa.foto@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $teacherCreateResponse = $this->post('/admin/guru', [
            'user_id' => $teacherUser->id,
            'nip' => '199000000111',
            'name' => 'Guru Foto',
            'is_subject_teacher' => true,
            'phone' => '081211111111',
            'address' => 'Jl. Guru Foto',
            'photo' => UploadedFile::fake()->image('guru.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $teacherCreateResponse->assertCreated();

        $teacherUser->refresh();

        $teacher = Teacher::query()->findOrFail($teacherCreateResponse->json('data.id'));

        $this->assertNotNull($teacher->photo_path);
        $this->assertNotNull($teacher->photo_url);
        Storage::disk('public')->assertExists($teacher->photo_path);
        $this->assertNotContains('guru_mapel', $teacherUser->roles ?? []);

        $studentCreateResponse = $this->post('/admin/siswa', [
            'user_id' => $studentUser->id,
            'nik' => '30001',
            'nisn' => '5000000001',
            'name' => 'Siswa Foto',
            'gender' => 'P',
            'birth_date' => '2010-07-20',
            'phone' => '081322222222',
            'address' => 'Jl. Siswa Foto',
            'photo' => UploadedFile::fake()->image('siswa.png'),
        ], [
            'Accept' => 'application/json',
        ]);

        $studentCreateResponse->assertCreated();

        $student = Student::query()->findOrFail($studentCreateResponse->json('data.id'));

        $this->assertNotNull($student->photo_path);
        $this->assertNotNull($student->photo_url);
        Storage::disk('public')->assertExists($student->photo_path);

        $teacherPhotoPath = $teacher->photo_path;
        $studentPhotoPath = $student->photo_path;

        $this->put("/admin/guru/{$teacher->id}", [
            'user_id' => $teacherUser->id,
            'nip' => '199000000111',
            'name' => 'Guru Foto',
            'is_subject_teacher' => true,
            'phone' => '081211111111',
            'address' => 'Jl. Guru Foto',
            'remove_photo' => true,
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $this->put("/admin/siswa/{$student->id}", [
            'user_id' => $studentUser->id,
            'nik' => '30001',
            'nisn' => '5000000001',
            'name' => 'Siswa Foto',
            'gender' => 'P',
            'birth_date' => '2010-07-20',
            'phone' => '081322222222',
            'address' => 'Jl. Siswa Foto',
            'remove_photo' => true,
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $teacher->refresh();
        $student->refresh();

        $this->assertNull($teacher->photo_path);
        $this->assertNull($student->photo_path);
        Storage::disk('public')->assertMissing($teacherPhotoPath);
        Storage::disk('public')->assertMissing($studentPhotoPath);
    }

    public function test_admin_can_create_and_update_student_detail_siswa(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $studentUser = User::query()->create([
            'name' => 'Siswa Detail',
            'email' => 'siswa.detail@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $createResponse = $this->postJson('/admin/siswa', [
            'user_id' => $studentUser->id,
            'nik' => '30011',
            'nisn' => '5000000011',
            'name' => 'Siswa Detail',
            'gender' => 'L',
            'birth_date' => '2010-08-21',
            'phone' => '081355555555',
            'address' => 'Alamat ringkas siswa',
            'detail_siswa' => [
                'religion' => 'Islam',
                'birth_place' => 'Bandung',
                'address_street' => 'Jl. Merdeka No. 12',
                'address_village' => 'Citarum',
                'address_district' => 'Bandung Wetan',
                'address_province' => 'Jawa Barat',
                'address_city' => 'Bandung',
                'father_name' => 'Bapak Siswa',
                'father_education' => 'S1',
                'father_occupation' => 'Wiraswasta',
                'mother_name' => 'Ibu Siswa',
                'mother_education' => 'SMA',
                'mother_occupation' => 'Ibu Rumah Tangga',
                'parent_address' => 'Jl. Merdeka No. 12',
                'parent_province' => 'Jawa Barat',
                'parent_city' => 'Bandung',
                'postal_code' => '40115',
                'parent_phone' => '081366666666',
                'previous_school' => 'SMP Negeri 1 Bandung',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.detail_siswa.religion', 'Islam')
            ->assertJsonPath('data.detail_siswa.birth_place', 'Bandung');

        $student = Student::query()->with('detailSiswa')->findOrFail($createResponse->json('data.id'));

        $this->assertNotNull($student->detailSiswa);
        $this->assertSame('Islam', $student->detailSiswa?->religion);
        $this->assertSame('Bandung', $student->detailSiswa?->birth_place);
        $this->assertSame('SMP Negeri 1 Bandung', $student->detailSiswa?->previous_school);

        $this->assertDatabaseHas('student_details', [
            'student_id' => $student->id,
            'father_name' => 'Bapak Siswa',
            'mother_name' => 'Ibu Siswa',
            'postal_code' => '40115',
        ]);

        $this->putJson("/admin/siswa/{$student->id}", [
            'user_id' => $studentUser->id,
            'nik' => '30011',
            'nisn' => '5000000011',
            'name' => 'Siswa Detail Diperbarui',
            'gender' => 'L',
            'birth_date' => '2010-08-21',
            'phone' => '081355555555',
            'address' => 'Alamat ringkas siswa diperbarui',
            'detail_siswa' => [
                'religion' => 'Kristen',
                'birth_place' => 'Jakarta',
                'address_street' => 'Jl. Sudirman No. 5',
                'address_village' => 'Karet',
                'address_district' => 'Setiabudi',
                'address_province' => 'DKI Jakarta',
                'address_city' => 'Jakarta Selatan',
                'father_name' => 'Bapak Baru',
                'father_education' => 'S2',
                'father_occupation' => 'Pegawai Swasta',
                'mother_name' => 'Ibu Baru',
                'mother_education' => 'S1',
                'mother_occupation' => 'Wiraswasta',
                'parent_address' => 'Jl. Sudirman No. 5',
                'parent_province' => 'DKI Jakarta',
                'parent_city' => 'Jakarta Selatan',
                'postal_code' => '12920',
                'parent_phone' => '081377777777',
                'previous_school' => 'SMP Negeri 3 Jakarta',
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Siswa Detail Diperbarui')
            ->assertJsonPath('data.detail_siswa.religion', 'Kristen')
            ->assertJsonPath('data.detail_siswa.parent_city', 'Jakarta Selatan');

        $student->refresh();
        $student->load('detailSiswa');

        $this->assertSame('Siswa Detail Diperbarui', $student->name);
        $this->assertSame('Kristen', $student->detailSiswa?->religion);
        $this->assertSame('Jakarta', $student->detailSiswa?->birth_place);
        $this->assertSame('SMP Negeri 3 Jakarta', $student->detailSiswa?->previous_school);
    }

    public function test_updating_student_resets_linked_user_password_to_birth_date(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $studentUser = User::query()->create([
            'name' => 'Siswa Password',
            'email' => 'siswa.password@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'nik' => '30021',
            'nisn' => '5000000021',
            'name' => 'Siswa Password',
            'gender' => 'P',
            'birth_date' => '2010-07-20',
        ]);

        $this->putJson("/admin/siswa/{$student->id}", [
            'user_id' => $studentUser->id,
            'nik' => '30021',
            'nisn' => '5000000021',
            'name' => 'Siswa Password Diperbarui',
            'gender' => 'P',
            'birth_date' => '2010-08-21',
        ])->assertOk();

        $studentUser->refresh();

        $this->assertTrue(Hash::check('21082010', $studentUser->password));
    }

    public function test_admin_students_page_renders_detail_siswa_fields(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        Student::query()->create([
            'nik' => '9988776655443322',
            'nisn' => '5000099123',
            'name' => 'Siswa Modal',
            'gender' => 'L',
            'birth_date' => '2010-01-15',
        ]);

        $this->get('/admin/data-siswa')
            ->assertOk()
            ->assertSee('Detail Siswa', false)
            ->assertSee('Tempat Lahir', false)
            ->assertSee('Pendidikan Asal', false)
            ->assertSee('Kontak Orang Tua / Wali', false)
            ->assertSee('Password Default', false)
            ->assertSee('Foto Siswa', false)
            ->assertSee('Lihat detail siswa Siswa Modal', false);
    }

    public function test_admin_teachers_page_renders_extended_teacher_fields(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        Teacher::query()->create([
            'nip' => '198900000099',
            'name' => 'Guru Detail',
            'position' => 'Wakil Kepala Sekolah',
        ]);

        $this->get('/admin/data-guru')
            ->assertOk()
            ->assertSee('Status Peran Otomatis', false)
            ->assertSee('Tempat Lahir', false)
            ->assertSee('Pendidikan Terakhir', false)
            ->assertSee('Mata Pelajaran', false)
            ->assertSee('Kelas Yang Diampu', false)
            ->assertSee('Lihat detail guru Guru Detail', false);
    }

    public function test_teacher_roles_are_derived_from_homeroom_and_teaching_assignments(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $teacherUser = User::query()->create([
            'name' => 'Guru Role',
            'email' => 'guru.role@sarunis.test',
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => [],
        ]);

        $teacherResponse = $this->postJson('/admin/guru', [
            'user_id' => $teacherUser->id,
            'nip' => '198900000077',
            'name' => 'Guru Role',
            'is_subject_teacher' => true,
        ])->assertCreated();

        $subjectResponse = $this->postJson('/admin/mapel', [
            'code' => 'FIS',
            'name' => 'Fisika',
            'description' => 'Fisika dasar',
        ])->assertCreated();

        $classResponse = $this->postJson('/admin/kelas', [
            'name' => 'XI IPA 1',
            'level' => 'XI',
            'academic_year' => '2025/2026',
            'description' => 'Kelas uji role guru',
        ])->assertCreated();

        $teacherId = $teacherResponse->json('data.id');
        $subjectId = $subjectResponse->json('data.id');
        $classId = $classResponse->json('data.id');

        $teacherUser->refresh();
        $this->assertNotContains('guru_mapel', $teacherUser->roles ?? []);

        $this->putJson("/admin/kelas/{$classId}/ploting", [
            'homeroom_teacher_id' => $teacherId,
            'student_ids' => [],
        ])->assertOk();

        $teacherUser->refresh();
        $this->assertContains('guru_mapel', $teacherUser->roles ?? []);

        $this->postJson('/admin/jadwal-ajar', [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'school_class_id' => $classId,
            'academic_year' => '2025/2026',
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'room' => 'R-301',
        ])->assertCreated();

        $teacherUser->refresh();
        $this->assertContains('guru_mapel', $teacherUser->roles ?? []);
    }

    public function test_homeroom_teacher_cannot_be_assigned_to_multiple_classes(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $teacherResponse = $this->postJson('/admin/guru', [
            'nip' => '198900000088',
            'name' => 'Guru Wali Tunggal',
            'is_subject_teacher' => true,
        ])->assertCreated();

        $teacherId = $teacherResponse->json('data.id');

        $classAResponse = $this->postJson('/admin/kelas', [
            'name' => 'XII VALID A',
            'level' => 'XII',
            'academic_year' => '2025/2026',
            'homeroom_teacher_id' => $teacherId,
            'description' => 'Kelas wali pertama',
        ])->assertCreated();

        $this->postJson('/admin/kelas', [
            'name' => 'XII VALID B',
            'level' => 'XII',
            'academic_year' => '2025/2026',
            'homeroom_teacher_id' => $teacherId,
            'description' => 'Kelas wali kedua',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['homeroom_teacher_id']);

        $classBResponse = $this->postJson('/admin/kelas', [
            'name' => 'XII VALID C',
            'level' => 'XII',
            'academic_year' => '2025/2026',
            'description' => 'Kelas tanpa wali',
        ])->assertCreated();

        $this->putJson("/admin/kelas/{$classBResponse->json('data.id')}/ploting", [
            'homeroom_teacher_id' => $teacherId,
            'student_ids' => [],
            'subject_ids' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['homeroom_teacher_id']);

        $this->putJson("/admin/kelas/{$classAResponse->json('data.id')}", [
            'name' => 'XII VALID A',
            'level' => 'XII',
            'academic_year' => '2025/2026',
            'homeroom_teacher_id' => $teacherId,
            'description' => 'Kelas wali pertama tetap',
        ])->assertOk();

        $this->get('/admin/data-kelas')
            ->assertOk()
            ->assertSee('Guru yang sudah menjadi wali kelas tidak dapat dipilih untuk kelas lain.', false);
    }

    public function test_admin_crud_validation_rejects_invalid_payloads(): void
    {
        $admin = User::query()->where('email', 'admin@sarunis.test')->firstOrFail();
        $this->actingAs($admin);

        $this->postJson('/admin/guru', [
            'nip' => '##',
            'name' => 'A',
            'is_subject_teacher' => 'invalid',
            'phone' => '123',
            'address' => str_repeat('x', 1001),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['nip', 'name', 'is_subject_teacher', 'phone', 'address']);

        $this->postJson('/admin/siswa', [
            'nik' => '1',
            'nisn' => 'abc',
            'name' => 'B',
            'gender' => 'X',
            'birth_date' => now()->addDay()->toDateString(),
            'phone' => '123',
            'detail_siswa' => [
                'postal_code' => 'kode',
                'parent_phone' => '123',
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['nik', 'nisn', 'name', 'gender', 'birth_date', 'phone', 'detail_siswa.postal_code', 'detail_siswa.parent_phone']);

        $this->postJson('/admin/kelas', [
            'name' => 'X IPA 1',
            'level' => 'X@',
            'academic_year' => '2025/2026',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'level']);

        $this->postJson('/admin/mapel', [
            'code' => 'MT 1',
            'name' => 'A',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        $guruMapel = Teacher::query()->where('nip', '198801010001')->firstOrFail();
        $assignment = $guruMapel->teachingAssignments()->firstOrFail();

        $this->postJson('/admin/jadwal-ajar', [
            'teacher_id' => $guruMapel->id,
            'subject_id' => $assignment->subject_id,
            'school_class_id' => $assignment->school_class_id,
            'academic_year' => '2025/2026',
            'day_of_week' => $assignment->day_of_week,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'R-105',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['teacher_id', 'school_class_id']);
    }
}
