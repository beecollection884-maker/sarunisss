<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_calendars', function (Blueprint $table) {
            $table->string('type', 50)->default('event_sekolah')->after('category');
            $table->index(['academic_year', 'semester', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('academic_calendars', function (Blueprint $table) {
            $table->dropIndex(['academic_year', 'semester', 'type']);
            $table->dropColumn('type');
        });
    }
};
