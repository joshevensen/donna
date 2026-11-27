<?php

namespace Database\Factories;

use App\Enums\ExtractionRunStatus;
use App\Models\ExtractionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtractionRun>
 */
class ExtractionRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'window_start' => now()->subDay(),
            'window_end' => now(),
            'batch_id' => 'msgbatch_'.fake()->unique()->lexify('????????'),
            'status' => ExtractionRunStatus::Submitted,
        ];
    }

    /**
     * A run whose results are already in memory.
     */
    public function applied(): static
    {
        return $this->state([
            'status' => ExtractionRunStatus::Applied,
            'results_applied_at' => now(),
        ]);
    }
}
