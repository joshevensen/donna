<?php

namespace App\Http\Resources;

use App\Conversation\Bubbles;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'created_at' => $this->created_at,
        ];
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
