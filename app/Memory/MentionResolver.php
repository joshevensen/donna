<?php

namespace App\Memory;

use App\Enums\PersonKind;
use App\Memory\Context\Mentions;
use App\Models\Person;
use App\Support\CodeFences;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds `@Name` in a message and matches each to the people Donna knows.
 * All of it is string matching: whether an unknown name is a person at all
 * is left to her.
 */
class MentionResolver
{
    /**
     * An `@` at the start or after whitespace, then a name: letters, digits
     * and underscores, with apostrophes and hyphens allowed inside. The
     * quantifiers are possessive so a scoped package (`@vitejs/plugin-react`)
     * is refused whole instead of matching a shorter name.
     */
    private const string PATTERN = '/(?<!\S)@([\p{L}\p{N}_]++(?:[\'’\-][\p{L}\p{N}_]++)*+)(?!\/)/u';

    /**
     * The names mentioned with `@`, as typed, once each. Emails, scoped
     * packages and anything inside code are not mentions.
     *
     * @return list<string>
     */
    public static function names(string $text): array
    {
        preg_match_all(self::PATTERN, CodeFences::strip($text), $matches);

        return collect($matches[1])
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }

    /**
     * Match each mention in the text against the given people.
     *
     * @param  Collection<int, Person>  $people  Everyone Donna knows, Josh and herself included.
     */
    public function resolve(string $text, Collection $people): Mentions
    {
        $known = collect();
        $unknown = [];
        $ambiguous = [];

        foreach (self::names($text) as $typed) {
            [$name, $fits] = $this->match($typed, $people);

            // Josh and Donna are always in context; mentioning them adds nothing.
            $others = $fits->filter(fn (Person $person): bool => $person->kind === PersonKind::Other)->values();

            match (true) {
                $fits->isEmpty() => $unknown[] = $name,
                $others->count() === 1 => $known->push($others->first()),
                $others->count() > 1 => $ambiguous[$name] = $others,
                default => null,
            };
        }

        return new Mentions(
            known: $known->unique(fn (Person $person): int => $person->id)->values(),
            unknown: array_values(array_unique($unknown)),
            ambiguous: $ambiguous,
        );
    }

    /**
     * Who fits a mention. "@Dave's" is tried as written first (someone may
     * really be called that), then as "Dave".
     *
     * @param  Collection<int, Person>  $people
     * @return array{0: string, 1: Collection<int, Person>} The name that was matched, and who fits it.
     */
    private function match(string $typed, Collection $people): array
    {
        $bare = preg_replace('/[\'’]s$/iu', '', $typed);

        foreach (array_unique([$typed, $bare]) as $name) {
            $fits = $this->fits($name, $people);

            if ($fits->isNotEmpty()) {
                return [$name, $fits];
            }
        }

        return [$bare, collect()];
    }

    /**
     * People whose name or alias is the mention; failing that, people whose
     * name or alias starts with it ("@Sarah" for Sarah Lark).
     *
     * @param  Collection<int, Person>  $people
     * @return Collection<int, Person>
     */
    private function fits(string $name, Collection $people): Collection
    {
        // An underscore stands in for the space a mention can't contain.
        $name = str_replace('_', ' ', $name);

        $exact = $people->filter(fn (Person $person): bool => collect($person->names())
            ->contains(fn (string $known): bool => NameMatcher::same($known, $name)));

        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        return $people->filter(fn (Person $person): bool => collect($person->names())
            ->contains(fn (string $known): bool => NameMatcher::same(Str::before(trim($known), ' '), $name)))
            ->values();
    }
}
