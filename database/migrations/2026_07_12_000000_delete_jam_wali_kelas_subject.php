<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Subject;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Deleting the subject named 'Jam Wali Kelas' from the database
        // Cascade delete on relationships should handle dependent assignments/schedules if defined,
        // otherwise we safely delete them.
        Subject::where('name', 'Jam Wali Kelas')->get()->each(function ($subject) {
            $subject->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollbacks needed for deleting dummy subject
    }
};
