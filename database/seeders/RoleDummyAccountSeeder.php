<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Seeder;

class RoleDummyAccountSeeder extends Seeder
{
    public function run(): void
    {
        $userRoleService = app(UserRoleService::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@sarunis.test'],
            [
                'name' => 'Admin Sekolah',
                'password' => 'password',
                'email_verified_at' => now(),
                'roles' => [UserRole::ADMIN->value],
            ],
        );

        $guruMapelUser = User::query()->updateOrCreate(
            ['email' => 'guru.mapel@sarunis.test'],
            [
                'name' => 'Guru Mapel',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $orangTuaUser = User::query()->updateOrCreate(
            ['email' => 'orangtua@sarunis.test'],
            [
                'name' => 'Orang Tua Demo',
                'password' => 'password',
                'email_verified_at' => now(),
                'roles' => [UserRole::ORANG_TUA->value],
            ],
        );

        $siswaUser = User::query()->updateOrCreate(
            ['email' => 'siswa@sarunis.test'],
            [
                'name' => 'Siswa Demo',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $guruMapel = Teacher::query()->updateOrCreate(
            ['nip' => '198801010001'],
            [
                'user_id' => $guruMapelUser->id,
                'name' => 'Budi Santoso',
                'is_subject_teacher' => true,
                'phone' => '081200000101',
                'address' => 'Jl. Mawar No. 10',
            ],
        );

        $guruWaliClass = SchoolClass::query()->updateOrCreate(
            [
                'name' => 'X IPA 1',
                'academic_year' => '2025/2026',
            ],
            [
                'level' => 'X',
                'homeroom_teacher_id' => $guruMapel->id,
                'description' => 'Kelas demo untuk akun guru sekaligus wali kelas.',
            ],
        );

        Student::query()->updateOrCreate(
            ['nik' => '10001'],
            [
                'user_id' => $siswaUser->id,
                'parent_user_id' => $orangTuaUser->id,
                'school_class_id' => $guruWaliClass->id,
                'nisn' => '3000000001',
                'name' => 'Andi Saputra',
                'gender' => 'L',
                'birth_date' => '2010-01-10',
                'phone' => '081300000201',
                'address' => 'Jl. Flamboyan No. 1',
            ],
        );

        Student::query()->updateOrCreate(
            ['nik' => '10002'],
            [
                'school_class_id' => $guruWaliClass->id,
                'nisn' => '3000000002',
                'name' => 'Bunga Maharani',
                'gender' => 'P',
                'birth_date' => '2010-02-11',
                'phone' => '081300000202',
                'address' => 'Jl. Flamboyan No. 2',
            ],
        );

        Student::query()->updateOrCreate(
            ['nik' => '10003'],
            [
                'school_class_id' => $guruWaliClass->id,
                'nisn' => '3000000003',
                'name' => 'Cahyo Nugroho',
                'gender' => 'L',
                'birth_date' => '2010-03-12',
                'phone' => '081300000203',
                'address' => 'Jl. Flamboyan No. 3',
            ],
        );

        $matematika = Subject::query()->updateOrCreate(
            ['code' => 'MAT'],
            [
                'name' => 'Matematika',
                'description' => 'Mapel demo Matematika.',
            ],
        );

        $biologi = Subject::query()->updateOrCreate(
            ['code' => 'BIO'],
            [
                'name' => 'Biologi',
                'description' => 'Mapel demo Biologi.',
            ],
        );

        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $guruMapel->id,
                'subject_id' => $matematika->id,
                'school_class_id' => $guruWaliClass->id,
                'academic_year' => '2025/2026',
                'day_of_week' => 1,
                'start_time' => '07:00',
                'end_time' => '08:30',
            ],
            [
                'room' => 'R-101',
            ],
        );

        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $guruMapel->id,
                'subject_id' => $biologi->id,
                'school_class_id' => $guruWaliClass->id,
                'academic_year' => '2025/2026',
                'day_of_week' => 2,
                'start_time' => '09:00',
                'end_time' => '10:30',
            ],
            [
                'room' => 'Lab Bio',
            ],
        );

        $admin->forceFill([
            'roles' => [UserRole::ADMIN->value],
        ])->save();

        $guruMapel->load('user');
        $siswa = Student::query()->where('user_id', $siswaUser->id)->first();

        $userRoleService->syncTeacherRoles($guruMapel);

        if ($siswa !== null) {
            $siswa->load('user');
            $userRoleService->syncStudentRole($siswa);
        }
    }
}
