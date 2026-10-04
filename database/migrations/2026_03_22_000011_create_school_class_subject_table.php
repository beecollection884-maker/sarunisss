<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });

        $pairs = DB::table('teaching_assignments')
            ->select(['school_class_id', 'subject_id'])
            ->distinct()
            ->get()
            ->map(fn (object $pair): array => [
                'school_class_id' => $pair->school_class_id,
                'subject_id' => $pair->subject_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($pairs !== []) {
            DB::table('school_class_subject')->insert($pairs);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_subject');
    }
};
