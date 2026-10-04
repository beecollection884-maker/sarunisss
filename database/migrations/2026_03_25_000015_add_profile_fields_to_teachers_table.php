<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('nik', 30)->nullable()->unique()->after('user_id');
            $table->string('birth_place')->nullable()->after('name');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('gender', 2)->nullable()->after('birth_date');
            $table->string('religion', 100)->nullable()->after('gender');
            $table->string('employment_status')->nullable()->after('religion');
            $table->string('position')->nullable()->after('employment_status');
            $table->date('join_date')->nullable()->after('position');
            $table->string('last_education')->nullable()->after('join_date');
            $table->string('major')->nullable()->after('last_education');
            $table->string('university')->nullable()->after('major');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropUnique('teachers_nik_unique');
            $table->dropColumn([
                'nik',
                'birth_place',
                'birth_date',
                'gender',
                'religion',
                'employment_status',
                'position',
                'join_date',
                'last_education',
                'major',
                'university',
            ]);
        });
    }
};
