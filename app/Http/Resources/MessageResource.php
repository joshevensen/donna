<?php

namespace App\Http\Resources;

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
            'source' => $this->source,
            'status' => $this->status,
            'in_reply_to_id' => $this->in_reply_to_id,
            'error' => $this->error,
            'citations' => $this->meta['citations'] ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
