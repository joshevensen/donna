<?php

namespace App\Jobs;

use App\Memory\Embedder;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a message so hybrid retrieval can find it by meaning.
 */
class EmbedMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public Message $message) {}

    public function handle(Embedder $embedder): void
    {
        if ($this->message->embedding !== null || blank($this->message->body)) {
            return;
        }

        $this->message->update([
            'embedding' => $embedder->embed($this->message->body, $this->message->inReplyTo ?? $this->message),
        ]);
    }
}
