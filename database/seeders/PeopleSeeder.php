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
        // Found through Josh's Person, not by email, so changing DONNA_USER_EMAIL
        // moves the one user to the new address instead of making a second.
        $user = Person::query()->where('kind', PersonKind::Self)->first()?->user
            ?? User::firstOrCreate(
                ['email' => config('donna.user.email')],
                ['name' => config('donna.user.name'), 'password' => Str::password(40)],
            );

        // The address is the only one that can sign in to the web app.
        if ($user->email !== config('donna.user.email')) {
            $user->update(['email' => config('donna.user.email')]);
        }

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
