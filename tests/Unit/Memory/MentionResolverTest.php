<?php

use App\Enums\PersonKind;
use App\Memory\MentionResolver;
use App\Models\Person;

/**
 * A person as the resolver sees them, without a database.
 *
 * @param  list<string>  $aliases
 */
function person(int $id, string $name, array $aliases = [], PersonKind $kind = PersonKind::Other): Person
{
    return (new Person)->forceFill(['id' => $id, 'name' => $name, 'aliases' => $aliases, 'kind' => $kind]);
}

// `@` means "this is someone you should know", so it must not fire on the
// other things an @ sign turns up in.
it('finds @ mentions and nothing that only looks like one', function (string $text, array $names) {
    expect(MentionResolver::names($text))->toBe($names);
})->with([
    'at the start' => ['@Dave is coming Friday', ['Dave']],
    'after a space, before punctuation' => ['Lunch with @Dave, then @Kristen.', ['Dave', 'Kristen']],
    'after a line break' => ["Two things:\n@Dave called", ['Dave']],
    'apostrophes and hyphens inside a name' => ["@O'Brien and @Mary-Jane and @sam_w", ["O'Brien", 'Mary-Jane', 'sam_w']],
    'a possessive stays as typed' => ["@Dave's car", ["Dave's"]],
    'the same person twice is one mention' => ['@Dave and @dave again', ['Dave']],
    'an email address' => ['Send it to dave@example.com', []],
    'a scoped package' => ['Add @vitejs/plugin-react and @types/node', []],
    'an @ glued to the word before it' => ['user@Dave and (@Dave)', []],
    'inline code' => ['Run `npm i @Dave` then ask @Kristen', ['Kristen']],
    'a fenced code block' => ["```\n@Dave\n```\n@Kristen", ['Kristen']],
    'a lone @' => ['meet @ noon', []],
]);

it('matches a mention to the one person it names', function (string $text, int $personId) {
    $people = collect([
        person(1, 'Josh', kind: PersonKind::Self),
        person(2, 'Kristen', ['Kris']),
        person(3, 'Sarah Lark'),
        person(4, "Dave's Auto"),
    ]);

    $mentions = (new MentionResolver)->resolve($text, $people);

    expect($mentions->known->pluck('id')->all())->toBe([$personId])
        ->and($mentions->unknown)->toBe([])
        ->and($mentions->ambiguous)->toBe([]);
})->with([
    'by name, whatever the case' => ['ask @kristen', 2],
    'by alias' => ['ask @Kris', 2],
    'by first name' => ['ask @Sarah', 3],
    'by full name with an underscore for the space' => ['ask @Sarah_Lark', 3],
    'as a possessive' => ["@Kristen's order", 2],
    'a name that really ends in an apostrophe-s' => ["@Dave's quote came in", 4],
]);

it('lists a mention nobody matches as unknown, without the possessive', function () {
    $mentions = (new MentionResolver)->resolve("@Dave's truck and @dependabot", collect([person(2, 'Kristen')]));

    expect($mentions->unknown)->toBe(['Dave', 'dependabot'])
        ->and($mentions->known)->toBeEmpty();
});

it('flags a mention more than one person fits, with who', function () {
    $people = collect([person(2, 'Dave Miller'), person(3, 'Dave Chen'), person(4, 'Kristen')]);

    $mentions = (new MentionResolver)->resolve('call @Dave', $people);

    expect(array_keys($mentions->ambiguous))->toBe(['Dave'])
        ->and($mentions->ambiguous['Dave']->pluck('id')->all())->toBe([2, 3])
        ->and($mentions->known)->toBeEmpty()
        ->and($mentions->people()->pluck('id')->all())->toBe([2, 3]);
});

it('prefers the person named exactly over people who only start with the name', function () {
    $people = collect([person(2, 'Dave'), person(3, 'Dave Chen')]);

    $mentions = (new MentionResolver)->resolve('call @Dave', $people);

    expect($mentions->known->pluck('id')->all())->toBe([2])
        ->and($mentions->ambiguous)->toBe([]);
});

it('treats a mention of Josh or Donna as nothing new', function () {
    $people = collect([person(1, 'Josh', kind: PersonKind::Self), person(2, 'Donna', kind: PersonKind::Donna)]);

    $mentions = (new MentionResolver)->resolve('@Josh and @Donna', $people);

    expect($mentions->known)->toBeEmpty()
        ->and($mentions->unknown)->toBe([]);
});
