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

class TestAccountSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        $roleService = app(UserRoleService::class);

        User::query()->updateOrCreate(
            ['email' => 'test.admin@sarunis.test'],
            [
                'name' => 'Test Admin',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::ADMIN->value],
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'test.wakasek@sarunis.test'],
            [
                'name' => 'Test Wakasek Kesiswaan',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::WAKASEK_KESISWAAN->value],
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'test.guru-piket@sarunis.test'],
            [
                'name' => 'Test Guru Piket',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::GURU_PIKET->value],
            ],
        );

        $guruMapelUser = User::query()->updateOrCreate(
            ['email' => 'test.guru-mapel@sarunis.test'],
            [
                'name' => 'Test Guru Mapel',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::GURU_MAPEL->value],
            ],
        );

        $orangTuaUser = User::query()->updateOrCreate(
            ['email' => 'test.orang-tua@sarunis.test'],
            [
                'name' => 'Test Orang Tua',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::ORANG_TUA->value],
            ],
        );

        $siswaUser = User::query()->updateOrCreate(
            ['email' => 'test.siswa@sarunis.test'],
            [
                'name' => 'Test Siswa',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'roles' => [UserRole::SISWA->value],
            ],
        );

        $teacher = Teacher::query()->updateOrCreate(
            ['nip' => 'TEST-GURU-001'],
            [
                'user_id' => $guruMapelUser->id,
                'name' => 'Test Guru Mapel',
                'is_subject_teacher' => true,
                'phone' => '080000000001',
                'address' => 'Alamat test guru mapel',
            ],
        );

        $schoolClass = SchoolClass::query()->updateOrCreate(
            [
                'name' => 'TEST 1',
                'academic_year' => '2026/2027',
            ],
            [
                'level' => 'TEST',
                'homeroom_teacher_id' => $teacher->id,
                'description' => 'Kelas test untuk akun seeder production.',
            ],
        );

        $subject = Subject::query()->updateOrCreate(
            ['code' => 'TEST-MAPEL'],
            [
                'name' => 'Mapel Test',
                'lesson_hours' => 2,
                'description' => 'Mata pelajaran test untuk akun guru mapel.',
            ],
        );

        $teacher->subjects()->syncWithoutDetaching([$subject->id]);
        $schoolClass->subjects()->syncWithoutDetaching([$subject->id]);

        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'school_class_id' => $schoolClass->id,
                'academic_year' => '2026/2027',
                'day_of_week' => 1,
                'start_time' => '07:00',
            ],
            [
                'end_time' => '08:30',
                'room' => 'Ruang Test',
            ],
        );

        $student = Student::query()->updateOrCreate(
            ['nik' => 'TEST-SISWA-001'],
            [
                'user_id' => $siswaUser->id,
                'parent_user_id' => $orangTuaUser->id,
                'school_class_id' => $schoolClass->id,
                'nisn' => '9990000001',
                'name' => 'Test Siswa',
                'gender' => 'L',
                'birth_date' => '2012-01-01',
                'phone' => '080000000002',
                'address' => 'Alamat test siswa',
            ],
        );

        $teacher->load('user');
        $student->load('user');

        $roleService->syncTeacherRoles($teacher);
        $roleService->syncStudentRole($student);
    }
}
