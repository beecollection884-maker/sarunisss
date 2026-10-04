<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL', env('ADMIN_EMAIL'));
        $password = env('INITIAL_ADMIN_PASSWORD', env('ADMIN_PASSWORD'));

        if (blank($email) || blank($password)) {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $roles = array_values(array_unique([
            ...($user->roles ?? []),
            UserRole::ADMIN->value,
        ]));

        $user->forceFill([
            'name' => env('INITIAL_ADMIN_NAME', $user->name ?: 'Admin Sekolah'),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'roles' => $roles,
        ]);

        if (! $user->exists || env('INITIAL_ADMIN_SYNC_PASSWORD', false)) {
            $user->password = $password;
        }

        $user->save();
    }
}
