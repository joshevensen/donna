<?php

namespace Database\Factories;

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Models\Fact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fact>
 */
class FactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement' => fake()->sentence(),
            'kind' => FactKind::Fact,
            'confidence' => 1.0,
            'provenance' => FactProvenance::Extraction,
        ];
    }

    /**
     * A fact that the given newer fact replaced.
     */
    public function supersededBy(Fact $fact): static
    {
        return $this->state([
            'superseded_by_id' => $fact->id,
            'superseded_at' => now(),
        ]);
    }
}
