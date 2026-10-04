<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('portal')->index();
            $table->string('purpose')->default('password_reset')->index();
            $table->string('code_hash');
            $table->string('reset_token_hash')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'portal', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_verification_codes');
    }
};
