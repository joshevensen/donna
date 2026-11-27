<?php

namespace Database\Factories;

use App\Enums\PersonKind;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => PersonKind::Other,
            'name' => fake()->unique()->firstName(),
            'aliases' => [],
            'relationship' => 'friend',
            'summary' => null,
        ];
    }

    /**
     * Josh himself.
     */
    public function josh(): static
    {
        return $this->state([
            'kind' => PersonKind::Self,
            'name' => 'Josh',
            'relationship' => null,
        ]);
    }

    /**
     * Donna, the subject of her own shared history.
     */
    public function donna(): static
    {
        return $this->state([
            'kind' => PersonKind::Donna,
            'name' => 'Donna',
            'relationship' => null,
        ]);
    }
}
