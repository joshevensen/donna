<?php

namespace App\Ai\Contracts;

use App\Enums\CostPurpose;
use Illuminate\Database\Eloquent\Model;

/**
 * Every agent says what its calls are for and what set them off, so each
 * step it takes can be recorded as a Cost.
 */
interface TracksCost
{
    public function costPurpose(): CostPurpose;

    /**
     * The Message or ExtractionRun behind this call.
     */
    public function costTrigger(): ?Model;
}
