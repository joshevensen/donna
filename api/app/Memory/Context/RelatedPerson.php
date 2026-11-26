<?php

namespace App\Memory\Context;

use App\Models\Fact;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Someone the message names, or a retrieved fact is about.
 */
final readonly class RelatedPerson
{
    /**
     * @param  Collection<int, Fact>  $facts
     */
    public function __construct(
        public Person $person,
        public Collection $facts,
    ) {}
}
