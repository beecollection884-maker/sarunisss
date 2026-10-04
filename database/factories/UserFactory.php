<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'roles' => [UserRole::SISWA->value],
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->withRoles([UserRole::ADMIN]);
    }

    public function guruMapel(): static
    {
        return $this->withRoles([UserRole::GURU_MAPEL]);
    }

    public function guruDanWaliKelas(): static
    {
        return $this->withRoles([UserRole::GURU_MAPEL]);
    }

    public function siswa(): static
    {
        return $this->withRoles([UserRole::SISWA]);
    }

    public function orangTua(): static
    {
        return $this->withRoles([UserRole::ORANG_TUA]);
    }

    /**
     * @param array<int, UserRole|string> $roles
     */
    public function withRoles(array $roles): static
    {
        return $this->state(fn(array $attributes) => [
            'roles' => array_values(array_unique(array_map(
                static fn(UserRole|string $role): string => $role instanceof UserRole ? $role->value : $role,
                $roles,
            ))),
        ]);
    }
}
