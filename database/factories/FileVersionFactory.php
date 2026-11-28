<?php

namespace Database\Factories;

use App\Enums\MessageAuthor;
use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileVersion>
 */
class FileVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'file_id' => File::factory(),
            'number' => 1,
            'body' => $body,
            'content_hash' => hash('sha256', $body),
            'author' => MessageAuthor::Josh,
            'summary' => 'Uploaded',
            'created_at' => now(),
        ];
    }
}
