<?php

namespace App\Memory\Context;

use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Who Josh pointed at with `@` in one message, sorted by what matching found.
 */
final readonly class Mentions
{
    /**
     * @param  Collection<int, Person>  $known  People a mention matched, one each.
     * @param  list<string>  $unknown  Names that matched nobody.
     * @param  array<string, Collection<int, Person>>  $ambiguous  Names more than one person fits, with who.
     */
    public function __construct(
        public Collection $known,
        public array $unknown,
        public array $ambiguous,
    ) {}

    /**
     * Everyone a mention could mean: they all belong in the context.
     *
     * @return Collection<int, Person>
     */
    public function people(): Collection
    {
        return $this->known
            ->concat(collect($this->ambiguous)->flatten(1))
            ->unique(fn (Person $person): int => $person->id)
            ->values();
    }
}
