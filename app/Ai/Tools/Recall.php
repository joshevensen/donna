<?php

namespace App\Ai\Tools;

use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Memory\Embedder;
use App\Memory\HybridSearch;
use App\Memory\MemoryRenderer;
use App\Memory\Recollection;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class Recall implements Tool
{
    public function __construct(
        private Message $message,
        private Embedder $embedder,
        private HybridSearch $search,
        private Recollection $recollection,
        private MemoryRenderer $renderer,
    ) {}

    public function name(): string
    {
        return 'recall';
    }

    public function description(): string
    {
        return 'Search your memory of everything Josh and you have said, and everything you know, for a specific query. '
            .'Use when what you were given is not enough to answer, before saying you do not remember.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['query' => ['required', 'string', 'max:2000']]);

        $embedding = $this->embedder->attempt($input['query'], $this->message);
        $limit = config('donna.context.recall_limit');

        $exchanges = $this->recollection->exchanges(
            $this->search->messages($input['query'], $embedding, exceptIds: [$this->message->id]),
            $limit,
        );

        $facts = $this->recollection->facts($this->search->facts($input['query'], $embedding), $limit);

        $this->recollection->markRetrieved($facts);

        if ($exchanges->isEmpty() && $facts->isEmpty()) {
            return 'Nothing in memory matches that.';
        }

        return implode("\n\n", array_filter([
            $facts->isEmpty() ? null : "What you know:\n".$facts
                ->map(fn (RelatedFact $related): string => $this->renderer->fact($related->fact))
                ->implode("\n"),
            $exchanges->isEmpty() ? null : "Past exchanges:\n".$exchanges
                ->map(fn (RelatedExchange $exchange): string => $this->renderer->exchange($exchange))
                ->implode("\n"),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to look for, in plain words.')->required(),
        ];
    }
}
