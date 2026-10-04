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
            $table->foreignId('substitute_teacher_id')
                ->nullable()
                ->constrained('teachers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropForeign(['substitute_teacher_id']);
            $table->dropColumn('substitute_teacher_id');
        });
    }
};
