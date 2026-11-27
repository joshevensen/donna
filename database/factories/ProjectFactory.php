<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTier;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'aliases' => [],
            'tier' => ProjectTier::Main,
            'status' => ProjectStatus::Active,
            'summary' => null,
            'status_changed_at' => now(),
        ];
    }

    /**
     * A project Donna has suggested tracking but Josh has not confirmed.
     */
    public function proposed(): static
    {
        return $this->state(['status' => ProjectStatus::Proposed, 'tier' => null]);
    }

    public function parked(): static
    {
        return $this->state(['status' => ProjectStatus::Parked]);
    }

    public function dropped(): static
    {
        return $this->state(['status' => ProjectStatus::Dropped]);
    }
}
