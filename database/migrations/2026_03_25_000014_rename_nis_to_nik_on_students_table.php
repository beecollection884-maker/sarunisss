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
        if (Schema::hasColumn('students', 'nis') && ! Schema::hasColumn('students', 'nik')) {
            Schema::table('students', function (Blueprint $table) {
                $table->renameColumn('nis', 'nik');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('students', 'nik') && ! Schema::hasColumn('students', 'nis')) {
            Schema::table('students', function (Blueprint $table) {
                $table->renameColumn('nik', 'nis');
            });
        }
    }
};
