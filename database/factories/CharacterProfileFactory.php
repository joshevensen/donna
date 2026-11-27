<?php

namespace Database\Factories;

use App\Enums\CharacterProfileStatus;
use App\Models\CharacterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterProfile>
 */
class CharacterProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => 1,
            'body' => 'You are Donna.',
            'status' => CharacterProfileStatus::Active,
            'decided_at' => now(),
        ];
    }

    /**
     * A change Donna has proposed and Josh has not decided on.
     */
    public function proposed(): static
    {
        return $this->state([
            'version' => 2,
            'status' => CharacterProfileStatus::Proposed,
            'change_summary' => 'Sharper about deadlines.',
            'decided_at' => null,
        ]);
    }
}
