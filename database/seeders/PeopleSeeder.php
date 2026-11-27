<?php

namespace Database\Seeders;

use App\Enums\PersonKind;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PeopleSeeder extends Seeder
{
    /**
     * Seed Josh's login and the three people Donna knows from day one.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => config('donna.user.email')],
            ['name' => config('donna.user.name'), 'password' => Str::password(40)],
        );

        Person::firstOrCreate(
            ['kind' => PersonKind::Self],
            ['name' => $user->name, 'user_id' => $user->id],
        );

        Person::firstOrCreate(
            ['kind' => PersonKind::Donna],
            ['name' => 'Donna'],
        );

        Person::firstOrCreate(
            ['kind' => PersonKind::Other, 'name' => 'Kristen'],
            ['relationship' => 'wife'],
        );
    }
}
