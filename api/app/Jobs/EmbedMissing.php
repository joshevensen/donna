<?php

namespace App\Jobs;

use App\Memory\Embedder;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds anything that was saved without a vector because embeddings were
 * unavailable at the time. Until it runs, those rows are still found by
 * full-text search; afterwards, by meaning too.
 */
class EmbedMissing implements ShouldQueue
{
    use Queueable;

    /**
     * How many rows of each kind to embed per run, in one call each.
     */
    private const int BATCH = 100;

    public function handle(Embedder $embedder): void
    {
        $this->embed($embedder, Message::query()->complete()->where('body', '!=', ''), fn (Message $message): string => $message->body);
        $this->embed($embedder, Fact::query(), fn (Fact $fact): string => $fact->statement);
        $this->embed($embedder, Topic::query(), fn (Topic $topic): string => $topic->summary === null
            ? $topic->name
            : "{$topic->name}: {$topic->summary}");
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): string  $text
     */
    private function embed(Embedder $embedder, Builder $query, callable $text): void
    {
        $rows = $query->whereNull('embedding')->oldest('id')->limit(self::BATCH)->get();

        $embeddings = $embedder->embedMany($rows->map($text)->all());

        foreach ($rows as $index => $row) {
            $row->update(['embedding' => $embeddings[$index]]);
        }
    }
}
