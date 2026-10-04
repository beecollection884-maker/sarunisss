<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'teacher_id']);
        });

        $pairs = DB::table('teaching_assignments')
            ->select(['subject_id', 'teacher_id'])
            ->distinct()
            ->get()
            ->map(fn (object $pair): array => [
                'subject_id' => $pair->subject_id,
                'teacher_id' => $pair->teacher_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($pairs !== []) {
            DB::table('subject_teacher')->insert($pairs);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');
    }
};
