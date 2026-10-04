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
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GuruKelasScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $roleService = app(UserRoleService::class);
        $academicYear = '2026/2027';

        // 1. Create or retrieve the User
        $user = User::query()->updateOrCreate(
            ['email' => 'guru.kelas@sarunis.test'],
            [
                'name' => 'Guru Kelas Demo',
                'password' => 'password123',
                'email_verified_at' => now(),
                'roles' => [UserRole::GURU_MAPEL->value],
            ]
        );

        // 2. Create or retrieve the Teacher profile
        $teacher = Teacher::query()->updateOrCreate(
            ['nip' => 'GK-DEMO-001'],
            [
                'user_id' => $user->id,
                'name' => 'Guru Kelas Demo',
                'is_subject_teacher' => true,
                'phone' => '081200009001',
                'address' => 'Alamat demo guru kelas',
            ]
        );

        // Sync roles
        $roleService->syncTeacherRoles($teacher);

        // 3. Create or retrieve the Homeroom class
        $schoolClass = SchoolClass::query()->updateOrCreate(
            [
                'name' => 'DEMO KELAS HARI INI',
                'academic_year' => $academicYear,
            ],
            [
                'level' => 'DEMO',
                'homeroom_teacher_id' => $teacher->id,
                'description' => 'Kelas demo utama untuk Guru Kelas.',
            ]
        );

        // 4. Create dummy students for this class
        $studentNames = ['Andi', 'Bella', 'Citra', 'Dani'];
        foreach ($studentNames as $idx => $name) {
            $num = $idx + 1;
            Student::query()->updateOrCreate(
                ['nik' => "GK-S-00{$num}"],
                [
                    'school_class_id' => $schoolClass->id,
                    'nisn' => "888000000{$num}",
                    'name' => "Siswa Demo {$name}",
                    'gender' => ($idx % 2 === 0) ? 'L' : 'P',
                    'birth_date' => '2012-05-15',
                    'phone' => "08123456789{$num}",
                    'address' => "Alamat siswa demo {$num}",
                ]
            );
        }

        // 5. Create or retrieve subjects
        $subjectsData = [
            ['code' => 'MP-AKTIF', 'name' => 'Mapel Sesi Aktif'],
            ['code' => 'MP-LAMPAU', 'name' => 'Mapel Sesi Lampau'],
            ['code' => 'MP-DEPAN', 'name' => 'Mapel Sesi Depan'],
        ];

        $subjects = [];
        foreach ($subjectsData as $data) {
            $subject = Subject::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'lesson_hours' => 2,
                    'description' => "Mata pelajaran demo {$data['name']}.",
                ]
            );
            $subjects[$data['code']] = $subject;

            // Sync with teacher and class
            $teacher->subjects()->syncWithoutDetaching([$subject->id]);
            $schoolClass->subjects()->syncWithoutDetaching([$subject->id]);
        }

        // 6. Generate 3 dynamic teaching assignments for TODAY
        $todayDayOfWeek = Carbon::now()->dayOfWeekIso - 1; // 0 (Mon) to 6 (Sun)
        $now = Carbon::now();

        // Sesi 1: Active Now (e.g., from 1 hour ago to 1 hour from now)
        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'subject_id' => $subjects['MP-AKTIF']->id,
                'school_class_id' => $schoolClass->id,
                'academic_year' => $academicYear,
                'day_of_week' => $todayDayOfWeek,
                'start_time' => $now->copy()->subHour()->format('H:i'),
            ],
            [
                'end_time' => $now->copy()->addHour()->format('H:i'),
                'room' => 'Ruang Demo 1',
            ]
        );

        // Sesi 2: Finished / Past (e.g., from 3 hours ago to 2 hours ago)
        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'subject_id' => $subjects['MP-LAMPAU']->id,
                'school_class_id' => $schoolClass->id,
                'academic_year' => $academicYear,
                'day_of_week' => $todayDayOfWeek,
                'start_time' => $now->copy()->subHours(3)->format('H:i'),
            ],
            [
                'end_time' => $now->copy()->subHours(2)->format('H:i'),
                'room' => 'Ruang Demo 2',
            ]
        );

        // Sesi 3: Future (e.g., from 2 hours from now to 3 hours from now)
        TeachingAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'subject_id' => $subjects['MP-DEPAN']->id,
                'school_class_id' => $schoolClass->id,
                'academic_year' => $academicYear,
                'day_of_week' => $todayDayOfWeek,
                'start_time' => $now->copy()->addHours(2)->format('H:i'),
            ],
            [
                'end_time' => $now->copy()->addHours(3)->format('H:i'),
                'room' => 'Ruang Demo 3',
            ]
        );

        echo "Seeded schedule for guru.kelas@sarunis.test successfully for day_of_week={$todayDayOfWeek}!" . PHP_EOL;
    }
}
