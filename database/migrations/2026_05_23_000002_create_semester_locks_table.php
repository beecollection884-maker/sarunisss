<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_locks', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->timestamp('locked_at');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_locks');
    }
};
