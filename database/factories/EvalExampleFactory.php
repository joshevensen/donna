<?php

namespace Database\Factories;

use App\Enums\EvalRating;
use App\Models\EvalExample;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvalExample>
 */
class EvalExampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory()->fromDonna(),
            'rating' => EvalRating::Good,
            'note' => null,
        ];
    }
}
