<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = DatabaseSeeder::class;

    /**
     * Test admin can access schedule generate form data.
     */
    public function test_admin_can_access_schedule_generate_page(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/schedule/form-data');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['classes', 'teachers', 'subjects']]);
    }

    /**
     * Test admin can validate data before schedule generation.
     */
    public function test_admin_can_validate_data_before_generation(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/schedule/generate', [
                'academic_year' => '2025/2026',
                'validate_only' => true,
                'clear_existing' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'warnings'
            ]);
    }

    /**
     * Test admin can run schedule generator.
     */
    public function test_admin_can_generate_schedule(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        // Ambil tahun akademik aktif
        $academicYear = '2025/2026';

        // Pastikan kelas dan guru/mapel tersedia
        $class = SchoolClass::first();
        $teacher = Teacher::first();
        $subject = Subject::first();

        // Hubungkan guru ke mapel jika belum
        if ($subject && $teacher) {
            $subject->teachers()->syncWithoutDetaching([$teacher->id]);
            $class->subjects()->syncWithoutDetaching([$subject->id]);
        }

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/schedule/generate', [
                'academic_year' => $academicYear,
                'validate_only' => false,
                'clear_existing' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_classes',
                    'total_assignments',
                    'conflicts_detected',
                    'successful_slots',
                    'failed_slots',
                    'details',
                ]
            ]);
    }

    /**
     * Test admin can view class and teacher schedule and analysis reports.
     */
    public function test_admin_can_view_schedules_and_analyze(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $teacher = Teacher::firstOrFail();
        $academicYear = '2025/2026';

        // View Class Schedule
        $responseClass = $this->actingAs($admin)
            ->getJson("/api/v1/admin/schedule/class/{$class->id}/{$academicYear}");
        $responseClass->assertStatus(200)
            ->assertJsonPath('success', true);

        // View Teacher Schedule
        $responseTeacher = $this->actingAs($admin)
            ->getJson("/api/v1/admin/schedule/teacher/{$teacher->id}/{$academicYear}");
        $responseTeacher->assertStatus(200)
            ->assertJsonPath('success', true);

        // View Analyze Report
        $responseAnalyze = $this->actingAs($admin)
            ->getJson("/api/v1/admin/schedule/analyze/{$academicYear}");
        $responseAnalyze->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test admin can transfer teaching assignment to another teacher.
     */
    public function test_admin_can_transfer_teaching_assignment(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        
        // Find two distinct teachers
        $teachers = Teacher::limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $teachers->count());
        $teacherA = $teachers[0];
        $teacherB = $teachers[1];

        // Find or create an assignment for teacherA
        $class = SchoolClass::firstOrFail();
        $subject = Subject::firstOrFail();
        
        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherA->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_year' => '2025/2026',
            'day_of_week' => 0, // Monday
            'start_time' => '15:00',
            'end_time' => '16:00',
            'room' => 'Ruang X IPA 1'
        ]);

        $payload = [
            'teacher_id' => $teacherB->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_year' => '2025/2026',
            'day_of_week' => 0,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'room' => 'Ruang X IPA 1'
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/jadwal-ajar/{$assignment->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $teacherB->id,
        ]);
    }

    /**
     * Test admin can assign a substitute/picket teacher to teaching assignment.
     */
    public function test_admin_can_assign_substitute_teacher(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        
        $teachers = Teacher::limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $teachers->count());
        $teacherA = $teachers[0];
        $teacherB = $teachers[1];

        $class = SchoolClass::firstOrFail();
        $subject = Subject::firstOrFail();
        
        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacherA->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_year' => '2025/2026',
            'day_of_week' => 0, // Monday
            'start_time' => '16:00',
            'end_time' => '17:00',
            'room' => 'Ruang X IPA 1'
        ]);

        $payload = [
            'teacher_id' => $teacherA->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_year' => '2025/2026',
            'day_of_week' => 0,
            'start_time' => '16:00',
            'end_time' => '17:00',
            'room' => 'Ruang X IPA 1',
            'substitute_teacher_id' => $teacherB->id,
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/jadwal-ajar/{$assignment->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'substitute_teacher_id' => $teacherB->id,
        ]);
    }
}
