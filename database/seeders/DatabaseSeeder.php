<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Idempotent: safe to run on every deploy, and it never overwrites
     * anything Donna has learned since the first one.
     */
    public function run(): void
    {
        $this->call([
            PeopleSeeder::class,
            CharacterProfileSeeder::class,
            InterviewAreaSeeder::class,
        ]);
    }
}
