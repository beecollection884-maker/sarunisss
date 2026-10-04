<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->string('title');
            $table->string('category', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year', 'semester', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendars');
    }
};
