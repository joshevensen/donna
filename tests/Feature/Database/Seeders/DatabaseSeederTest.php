<?php

use App\Enums\CharacterProfileStatus;
use App\Enums\PersonKind;
use App\Models\CharacterProfile;
use App\Models\InterviewArea;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\seed;

it('seeds Josh, Donna, Kristen, the first character profile and the interview areas', function () {
    seed(DatabaseSeeder::class);

    $josh = Person::josh();

    expect($josh->user->is(User::sole()))->toBeTrue()
        ->and(Person::donna()->name)->toBe('Donna')
        ->and(Person::where('kind', PersonKind::Other)->sole())
        ->name->toBe('Kristen')
        ->relationship->toBe('wife')
        ->and(CharacterProfile::current())
        ->version->toBe(1)
        ->body->toStartWith('# Donna')
        ->and(InterviewArea::pluck('area')->all())->toBe([
            'Life story', 'Work', 'People', 'Interests', 'How Josh works', 'Goals', 'Josh & Donna',
        ]);
});

it('does not overwrite who Donna has become when seeded again', function () {
    seed(DatabaseSeeder::class);
    CharacterProfile::current()->update(['status' => CharacterProfileStatus::Superseded]);
    $current = CharacterProfile::factory()->create(['version' => 2, 'body' => 'You are Donna, a year in.']);
    InterviewArea::where('area', 'Work')->update(['notes' => 'Covered Fibermade.']);

    seed(DatabaseSeeder::class);

    expect(CharacterProfile::current()->is($current))->toBeTrue()
        ->and(CharacterProfile::count())->toBe(2)
        ->and(Person::count())->toBe(3)
        ->and(User::count())->toBe(1)
        ->and(InterviewArea::count())->toBe(7)
        ->and(InterviewArea::where('area', 'Work')->value('notes'))->toBe('Covered Fibermade.');
});

it('moves the one user to a changed DONNA_USER_EMAIL instead of creating a second', function () {
    seed(DatabaseSeeder::class);
    config(['donna.user.email' => 'new-address@example.com']);

    seed(DatabaseSeeder::class);

    expect(User::sole()->email)->toBe('new-address@example.com')
        ->and(Person::josh()->user->is(User::sole()))->toBeTrue();
});
