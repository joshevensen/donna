<?php

namespace App\Models;

use Database\Factories\FileChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of a kept file's current text, cut at a logical boundary and
 * embedded on its own: Archive Memory. Only kept files have chunks.
 */
#[Fillable(['file_id', 'position', 'heading_path', 'body', 'content_hash', 'embedding'])]
#[Hidden(['embedding', 'search'])]
class FileChunk extends Model
{
    /** @use HasFactory<FileChunkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * What is embedded: the text with where it sits, so a chunk about
     * "Wholesale" also knows it belongs to the pricing section of a plan.
     */
    public function embeddingText(): string
    {
        return self::textToEmbed($this->file->name, $this->heading_path, $this->body);
    }

    public static function textToEmbed(string $fileName, ?string $headingPath, string $body): string
    {
        return $fileName.(filled($headingPath) ? " › {$headingPath}" : '')."\n".$body;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'embedding' => AsVector::class,
        ];
    }
}
