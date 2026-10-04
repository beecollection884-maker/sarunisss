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
        Schema::create('student_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('religion')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address_street')->nullable();
            $table->string('address_village')->nullable();
            $table->string('address_district')->nullable();
            $table->string('address_province')->nullable();
            $table->string('address_city')->nullable();
            $table->string('father_name')->nullable();
            $table->string('father_education')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_education')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->text('parent_address')->nullable();
            $table->string('parent_province')->nullable();
            $table->string('parent_city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('previous_school')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_details');
    }
};
