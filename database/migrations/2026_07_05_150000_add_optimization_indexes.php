<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->index(['school_class_id', 'academic_year', 'day_of_week', 'start_time'], 'idx_ta_class_schedule');
            $table->index(['teacher_id', 'academic_year', 'day_of_week', 'start_time'], 'idx_ta_teacher_schedule');
        });

        Schema::table('student_violations', function (Blueprint $table) {
            $table->index('violation_date', 'idx_sv_violation_date');
        });

        Schema::table('class_attendances', function (Blueprint $table) {
            $table->index(['school_class_id', 'attendance_date'], 'idx_ca_class_date');
        });

        Schema::table('subject_attendances', function (Blueprint $table) {
            $table->index(['teaching_assignment_id', 'attendance_date'], 'idx_sa_assignment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_ta_class_schedule');
            $table->dropIndex('idx_ta_teacher_schedule');
        });

        Schema::table('student_violations', function (Blueprint $table) {
            $table->dropIndex('idx_sv_violation_date');
        });

        Schema::table('class_attendances', function (Blueprint $table) {
            $table->dropIndex('idx_ca_class_date');
        });

        Schema::table('subject_attendances', function (Blueprint $table) {
            $table->dropIndex('idx_sa_assignment_date');
        });
    }
};
