<?php

namespace App\Http\Resources;

use App\Enums\FileKind;
use App\Files\Csv;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A file as the web app lists it. `withContent()` adds what the viewer
 * draws: the text, or the first rows of a CSV.
 *
 * @mixin File
 */
class FileResource extends JsonResource
{
    private bool $content = false;

    /**
     * Include the file's text, for the viewer.
     */
    public function withContent(): static
    {
        $this->content = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'kept' => $this->isKept(),
            'deleted' => $this->trashed(),
            'extraction' => $this->extraction,
            'extraction_error' => $this->extraction_error,
            'meta' => (object) self::publicMeta($this->resource),
            'version' => $this->currentVersion?->number,
            'last_author' => $this->currentVersion?->author,
            'editable' => $this->isEditable(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            $this->mergeWhen($this->content, fn (): array => $this->content()),
        ];
    }

    /**
     * What a card or the viewer says about a file: its rows, pages or size
     * in pixels. Where things are stored is nobody's business but the server's.
     *
     * @return array<string, int|bool>
     */
    public static function publicMeta(File $file): array
    {
        return array_intersect_key($file->meta ?? [], array_flip(['rows', 'columns', 'pages', 'has_text', 'width', 'height']));
    }

    /**
     * @return array{body: string|null, truncated: bool, table: array{columns: list<string>, rows: list<list<string>>, total_rows: int}|null}
     */
    private function content(): array
    {
        $body = $this->body();

        if ($body === null) {
            return ['body' => null, 'truncated' => false, 'table' => null];
        }

        if ($this->kind === FileKind::Csv) {
            $csv = Csv::parse($body, config('donna.files.viewer_rows'));

            return [
                'body' => null,
                'truncated' => false,
                'table' => ['columns' => $csv->columns, 'rows' => $csv->rows, 'total_rows' => $csv->totalRows],
            ];
        }

        $limit = config('donna.files.viewer_bytes');

        return [
            'body' => strlen($body) > $limit ? mb_strcut($body, 0, $limit) : $body,
            'truncated' => strlen($body) > $limit,
            'table' => null,
        ];
    }
}
