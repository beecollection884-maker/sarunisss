<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sarunis.local';
        $parentDomain = 'sch.id';

        DB::table('users')
            ->where(function ($query) use ($domain, $parentDomain): void {
                $query->where('email', 'like', "guru.%@{$domain}")
                    ->orWhere('email', 'like', "siswa.%@{$domain}")
                    ->orWhere('email', 'like', "orangtua.%@{$parentDomain}");
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('teachers')
                    ->whereColumn('teachers.user_id', 'users.id');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.user_id', 'users.id');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.parent_user_id', 'users.id');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cleanup migration is irreversible.
    }
};
