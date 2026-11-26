<?php

use App\Ai\Contracts\TracksCost;

// Every LLM call records a Cost: an agent that cannot say what its calls are for must not exist.
arch('every agent tracks its cost')
    ->expect('App\Ai\Agents')
    ->toImplement(TracksCost::class);
