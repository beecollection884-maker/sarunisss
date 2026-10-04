<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keep production seeding free of showcase/demo data.
        // Run ShowcaseSeeder manually only when a local demo dataset is needed.
        if (app()->environment('testing')) {
            $this->call(ShowcaseSeeder::class);
        }
    }
}
