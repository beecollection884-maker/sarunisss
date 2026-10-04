<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSchedulePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_schedule_page_uses_configured_zero_based_school_days(): void
    {
        $studentUser = User::factory()->create([
            'roles' => [UserRole::SISWA->value],
        ]);
        $teacherUser = User::factory()->create([
            'roles' => [UserRole::GURU_MAPEL->value],
        ]);
        $schoolClass = SchoolClass::query()->create([
            'name' => 'X IPA 1',
            'level' => 'X',
            'academic_year' => '2025/2026',
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_class_id' => $schoolClass->id,
            'nik' => 'STU-001',
            'name' => 'Siswa Jadwal',
        ]);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'nip' => 'TCH-001',
            'name' => 'Guru Matematika',
            'is_subject_teacher' => true,
        ]);
        $subject = Subject::query()->create([
            'code' => 'MAT',
            'name' => 'Matematika',
        ]);

        TeachingAssignment::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $student->school_class_id,
            'academic_year' => '2025/2026',
            'day_of_week' => 0,
            'start_time' => '07:00',
            'end_time' => '08:30',
            'room' => 'R-101',
        ]);

        $this->actingAs($studentUser)
            ->get('/siswa/jadwal-mata-pelajaran')
            ->assertOk()
            ->assertSee('Senin')
            ->assertSee('Matematika')
            ->assertSee('Guru Matematika')
            ->assertSee('07:00 - 08:30');
    }
}
