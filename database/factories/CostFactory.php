<?php

namespace Database\Factories;

use App\Enums\CostPurpose;
use App\Models\Cost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cost>
 */
class CostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => CostPurpose::Reply,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 1000,
            'output_tokens' => 100,
            'usd' => '0.003000',
        ];
    }
}
