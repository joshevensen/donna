<?php

namespace Database\Factories;

use App\Enums\InterviewAreaStatus;
use App\Models\InterviewArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewArea>
 */
class InterviewAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area' => fake()->unique()->words(2, true),
            'status' => InterviewAreaStatus::Open,
        ];
    }
}
