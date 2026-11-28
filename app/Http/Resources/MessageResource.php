<?php

namespace App\Http\Resources;

use App\Conversation\Bubbles;
use App\Enums\FileRole;
use App\Models\File;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'body' => $this->body,
            // Only Donna splits a reply into several texts; what Josh typed is one.
            'bubbles' => $this->isFromDonna() ? Bubbles::split($this->body) : array_filter([$this->body], filled(...)),
            'source' => $this->source,
            'status' => $this->status,
            'in_reply_to_id' => $this->in_reply_to_id,
            'error' => $this->error,
            'citations' => $this->meta['citations'] ?? [],
            'sources' => $this->sources(),
            'actions' => $this->meta['actions'] ?? [],
            'files' => $this->files(),
            // The sending device's own key, so it can tell a send it wasn't sure of did arrive.
            'client_key' => $this->client_key,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * One card per file: what Josh attached, and what Donna created, edited
     * or opened in this reply. Each says whether the file is kept now, so a
     * keep is visible wherever the file appears.
     *
     * @return list<array<string, mixed>>
     */
    private function files(): array
    {
        $this->resource->loadMissing(['files', 'fileVersions' => fn ($query) => $query->select(['id', 'file_id', 'message_id', 'number', 'summary'])->orderBy('number')]);

        return $this->resource->files
            ->sortBy('id')
            ->groupBy('id')
            ->map(function (Collection $rows): array {
                /** @var File $file */
                $file = $rows->first();
                $role = $rows->map(fn (File $row): FileRole => FileRole::from($row->pivot->role))->sortByDesc(fn (FileRole $role): int => $role->weight())->first();
                $changes = $this->resource->fileVersions->where('file_id', $file->id)->pluck('summary');

                return [
                    'id' => $file->id,
                    'name' => $file->name,
                    'kind' => $file->kind,
                    'role' => $role,
                    'kept' => $file->isKept(),
                    'deleted' => $file->trashed(),
                    'size_bytes' => $file->size_bytes,
                    'meta' => (object) FileResource::publicMeta($file),
                    // What changed, for an edit's card. Written by code when the edit was made.
                    'note' => $role !== FileRole::Edited || $changes->isEmpty() ? null : ($changes->count() === 1 ? $changes->first() : $changes->count().' changes'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What the reply's context held, for "What I looked at": the web pages
     * she cited first, then the memory she was given.
     *
     * @return list<array{type: string, label: string, url?: string}>
     */
    private function sources(): array
    {
        $web = array_map(fn (array $citation): array => [
            'type' => 'web',
            'label' => $citation['title'] ?? $citation['url'],
            'url' => $citation['url'],
        ], $this->meta['citations'] ?? []);

        return array_slice([...$web, ...($this->meta['sources'] ?? [])], 0, config('donna.context.sources'));
    }
}
