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
        Schema::create('schedule_generations', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_classes')->default(0);
            $table->unsignedInteger('total_assignments')->default(0);
            $table->unsignedInteger('successful_slots')->default(0);
            $table->unsignedInteger('failed_slots')->default(0);
            $table->unsignedInteger('conflicts_detected')->default(0);
            $table->json('result_data')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('academic_year');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_generations');
    }
};
