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
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('level', 30);
            $table->string('academic_year', 20);
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'academic_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
