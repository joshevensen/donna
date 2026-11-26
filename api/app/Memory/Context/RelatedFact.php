<?php

namespace App\Memory\Context;

use App\Models\Fact;

/**
 * An active fact that retrieval surfaced. Its superseded predecessors ride
 * along on the model's `predecessors` relation.
 */
final readonly class RelatedFact
{
    public function __construct(
        public Fact $fact,
        public float $score,
    ) {}
}
