<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileChunk>
 */
class FileChunkFactory extends Factory
{
    /**
     * Define the model's default state: a chunk of a kept file.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'file_id' => File::factory()->kept(),
            'position' => 0,
            'heading_path' => null,
            'body' => $body,
            'content_hash' => hash('sha256', "\n".$body),
        ];
    }
}
