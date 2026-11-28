<?php

namespace Database\Factories;

use App\Enums\FileKind;
use App\Enums\FileRole;
use App\Enums\MessageAuthor;
use App\Models\File;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state: a working file with no text yet.
     * Use one of the kind states below to give it some.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2).'.md',
            'kind' => FileKind::Markdown,
            'mime' => 'text/markdown',
            'size_bytes' => 0,
        ];
    }

    /**
     * A markdown file holding the given text as its first version.
     */
    public function markdown(string $body = "# Notes\n\nNothing yet.\n"): static
    {
        return $this->state(['kind' => FileKind::Markdown, 'mime' => 'text/markdown'])->withBody($body);
    }

    public function csv(string $body = "name,amount\nYarn,12.50\n"): static
    {
        return $this
            ->state(fn (): array => [
                'name' => fake()->unique()->slug(2).'.csv',
                'kind' => FileKind::Csv,
                'mime' => 'text/csv',
                'meta' => ['rows' => max(0, substr_count(trim($body), "\n")), 'columns' => substr_count(strtok($body, "\n"), ',') + 1],
            ])
            ->withBody($body);
    }

    public function text(string $body = "plain text\n"): static
    {
        return $this
            ->state(fn (): array => ['name' => fake()->unique()->slug(2).'.json', 'kind' => FileKind::Text, 'mime' => 'text/plain'])
            ->withBody($body);
    }

    /**
     * A PDF whose pages had this text. Pass no pages for a scan.
     *
     * @param  list<string>  $pages
     */
    public function pdf(array $pages = ['Page one.'], int $scannedPages = 0): static
    {
        return $this
            ->state(fn (): array => [
                'name' => fake()->unique()->slug(2).'.pdf',
                'kind' => FileKind::Pdf,
                'mime' => 'application/pdf',
                'size_bytes' => 2048,
                'storage_path' => 'files/'.fake()->unique()->lexify('??????????').'.pdf',
                'meta' => ['pages' => count($pages) ?: $scannedPages, 'has_text' => $pages !== []],
            ])
            ->withBody(implode("\f", $pages ?: array_fill(0, $scannedPages, '')))
            ->afterCreating(fn (File $file) => Storage::put($file->storage_path, '%PDF-1.4 test'));
    }

    public function image(): static
    {
        return $this
            ->state(fn (): array => [
                'name' => fake()->unique()->slug(2).'.png',
                'kind' => FileKind::Image,
                'mime' => 'image/png',
                'size_bytes' => 1024,
                'storage_path' => 'files/'.fake()->unique()->lexify('??????????').'.png',
                'meta' => ['width' => 10, 'height' => 10],
            ])
            ->afterCreating(fn (File $file) => Storage::put($file->storage_path, 'png bytes'));
    }

    public function kept(): static
    {
        return $this->state(['kept_at' => now()]);
    }

    /**
     * Attached to (or otherwise touched by) the given message.
     */
    public function on(Message $message, FileRole $role = FileRole::Attached): static
    {
        return $this->afterCreating(fn (File $file) => $file->messages()->attach($message, ['role' => $role->value]));
    }

    /**
     * Give the file its first version.
     */
    public function withBody(string $body, MessageAuthor $author = MessageAuthor::Josh): static
    {
        return $this->afterCreating(function (File $file) use ($body, $author): void {
            $version = $file->versions()->create([
                'number' => 1,
                'body' => $body,
                'content_hash' => hash('sha256', $body),
                'author' => $author,
                'summary' => $author === MessageAuthor::Josh ? 'Uploaded' : 'Created',
                'created_at' => $file->created_at,
            ]);

            // Without touching `updated_at`, so a test can say when the file was last used.
            File::withoutTimestamps(fn () => $file->update([
                'current_version_id' => $version->id,
                ...($file->kind->isStored() ? [] : ['size_bytes' => strlen($body)]),
            ]));
        });
    }
}
