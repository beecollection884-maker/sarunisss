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

class SubjectScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = DatabaseSeeder::class;

    /**
     * Test admin can create a subject with schedule fields.
     */
    public function test_admin_can_create_subject_with_schedule_fields(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $schoolClass = SchoolClass::firstOrFail();

        $payload = [
            'code' => 'TEST-SUBJ',
            'name' => 'Mata Pelajaran Uji',
            'lesson_hours' => 3,
            'description' => 'Deskripsi mapel uji coba',
            'day_of_week' => 1, // Selasa
            'start_time' => '08:00',
            'end_time' => '10:00',
            'school_class_id' => $schoolClass->id,
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/mapel', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'TEST-SUBJ');
        $response->assertJsonPath('data.day_of_week', 1);
        $response->assertJsonPath('data.school_class_id', $schoolClass->id);

        $this->assertDatabaseHas('subjects', [
            'code' => 'TEST-SUBJ',
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'school_class_id' => $schoolClass->id,
        ]);
    }

    /**
     * Test validation rules reject invalid schedule values.
     */
    public function test_validation_rejects_invalid_schedule_values(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();

        // 1. Invalid day_of_week
        $payload = [
            'code' => 'TEST-SUBJ2',
            'name' => 'Mata Pelajaran Uji 2',
            'day_of_week' => 10, // Invalid (must be 0-6)
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/mapel', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['day_of_week']);

        // 2. End time before start time
        $payload = [
            'code' => 'TEST-SUBJ2',
            'name' => 'Mata Pelajaran Uji 2',
            'start_time' => '10:00',
            'end_time' => '09:00', // Invalid (must be after start_time)
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/mapel', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_time']);
    }

    /**
     * Test admin can update subject schedule fields.
     */
    public function test_admin_can_update_subject_schedule_fields(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $subject = Subject::firstOrFail();
        $schoolClass = SchoolClass::firstOrFail();

        $payload = [
            'code' => $subject->code,
            'name' => 'Nama Mapel Diperbarui',
            'lesson_hours' => 4,
            'day_of_week' => 3, // Kamis
            'start_time' => '11:00',
            'end_time' => '12:30',
            'school_class_id' => $schoolClass->id,
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/mapel/{$subject->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Nama Mapel Diperbarui',
            'day_of_week' => 3,
            'start_time' => '11:00',
            'end_time' => '12:30',
            'school_class_id' => $schoolClass->id,
        ]);
    }

    /**
     * Test auto plotting respects fixed subject schedule.
     */
    public function test_auto_plotting_respects_fixed_subject_schedule(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $schoolClass = SchoolClass::firstOrFail();
        $teacher = Teacher::firstOrFail();

        // 1. Create a subject with a fixed schedule for this class
        $subject = Subject::create([
            'code' => 'FIXED-SUBJ',
            'name' => 'Mata Pelajaran Tetap',
            'lesson_hours' => 2,
            'day_of_week' => 0, // Senin
            'start_time' => '07:30',
            'end_time' => '09:00',
            'school_class_id' => $schoolClass->id,
        ]);

        // Associate teacher to subject and class to subject
        $subject->teachers()->sync([$teacher->id]);
        $schoolClass->subjects()->syncWithoutDetaching([$subject->id]);

        // Run auto-plotting via POST request
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/schedule/generate', [
                'academic_year' => $schoolClass->academic_year,
                'clear_existing' => true,
                'validate_only' => false,
            ]);

        $response->assertStatus(200);

        // Verify that a TeachingAssignment was created for this subject at the exact time
        $this->assertDatabaseHas('teaching_assignments', [
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $schoolClass->id,
            'day_of_week' => 0,
            'start_time' => '07:30',
            'end_time' => '09:00',
        ]);
    }

    /**
     * Test auto plotting detects teacher conflict on fixed subject schedule and skips it.
     */
    public function test_auto_plotting_skips_fixed_subject_schedule_on_teacher_conflict(): void
    {
        $admin = User::where('email', 'admin@sarunis.test')->firstOrFail();
        $schoolClass1 = SchoolClass::firstOrFail();
        $teacher = Teacher::firstOrFail();

        // Query the second class for testing conflict
        $schoolClass2 = SchoolClass::where('name', 'X IPA 2')->firstOrFail();

        // 1. Create an assignment that occupies the teacher on Monday 07:30 - 09:00 in Class 2
        TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::first()->id, // Use an existing subject
            'school_class_id' => $schoolClass2->id,
            'academic_year' => $schoolClass1->academic_year,
            'day_of_week' => 0,
            'start_time' => '07:30',
            'end_time' => '09:00',
            'room' => 'R-202',
        ]);

        // 2. Create a subject with a fixed schedule for Class 1 using the same teacher at the same time
        $subject = Subject::create([
            'code' => 'FIXED-SUBJ2',
            'name' => 'Mata Pelajaran Tetap 2',
            'lesson_hours' => 2,
            'day_of_week' => 0, // Senin
            'start_time' => '07:30',
            'end_time' => '09:00',
            'school_class_id' => $schoolClass1->id,
        ]);

        $subject->teachers()->sync([$teacher->id]);
        $schoolClass1->subjects()->syncWithoutDetaching([$subject->id]);

        // Run auto-plotting for Class 1 (do not clear existing, so the conflict assignment in Class 2 is preserved)
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/schedule/generate', [
                'academic_year' => $schoolClass1->academic_year,
                'clear_existing' => false,
                'validate_only' => false,
            ]);

        $response->dump();
        $response->assertStatus(200);

        // Verify that the conflict subject was NOT scheduled (to prevent teacher clash)
        $this->assertDatabaseMissing('teaching_assignments', [
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $schoolClass1->id,
            'day_of_week' => 0,
            'start_time' => '07:30',
            'end_time' => '09:00',
        ]);
    }
}
