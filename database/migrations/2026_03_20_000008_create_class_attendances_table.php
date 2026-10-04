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
        Schema::create('class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('recorded_by_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_class_id', 'student_id', 'attendance_date'],
                'class_att_unique_day'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_attendances');
    }
};
