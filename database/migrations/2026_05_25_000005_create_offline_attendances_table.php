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
        Schema::create('offline_attendances', function (Blueprint $table) {
            $table->id();

            // Device identifier (tablet, phone, etc)
            $table->string('offline_device_id');

            // Relationships
            $table->foreignId('student_id')->constrained();
            $table->foreignId('teacher_id')->constrained();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('teaching_assignment_id')->nullable()->constrained();

            // Attendance info
            $table->enum('attendance_type', ['class', 'subject'])->default('class');
            $table->date('attendance_date');
            $table->enum('status', ['hadir', 'sakit', 'izin', 'alfa'])->default('hadir');
            $table->text('notes')->nullable();

            // Offline timing
            $table->dateTime('recorded_at');

            // Sync tracking
            $table->boolean('synced')->default(false);
            $table->dateTime('synced_at')->nullable();
            $table->text('sync_error')->nullable();

            // UUID for offline identification
            $table->uuid('uuid')->unique();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('offline_device_id');
            $table->index('student_id');
            $table->index('attendance_date');
            $table->index('synced');
            $table->index(['synced', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_attendances');
    }
};
