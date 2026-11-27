<?php

namespace Database\Factories;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    /**
     * Define the model's default state: a fresh code nobody has tried yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => Hash::make('123456'),
            'link_token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
            'attempts' => 0,
        ];
    }
}
