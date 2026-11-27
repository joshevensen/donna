<?php

use App\Ai\Agents\Donna;
use App\Enums\InterviewAreaStatus;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Memory\Context\RelatedPerson;
use App\Memory\Context\RelatedTopic;
use App\Memory\Context\ReplyContext;
use App\Models\CharacterProfile;
use App\Models\Fact;
use App\Models\InterviewArea;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Messages\Message as Turn;
use Laravel\Ai\Prompts\AgentPrompt;

use function Pest\Laravel\travelTo;

/**
 * Send Josh's message through the reply job and return the context Donna
 * was given for it.
 */
function contextFor(string $body): ReplyContext
{
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    Donna::fake(['Okay.']);

    GenerateReply::dispatchSync(pendingReplyTo($body));

    $context = null;

    Donna::assertPrompted(function (AgentPrompt $prompt) use (&$context): bool {
        $context = $prompt->agent->context;

        return true;
    });

    return $context;
}

/**
 * @return list<int>
 */
function exchangeIds(ReplyContext $context): array
{
    return $context->memory->exchanges->map(fn (RelatedExchange $exchange): int => $exchange->message->id)->all();
}

/**
 * @return list<int>
 */
function factIds(ReplyContext $context): array
{
    return $context->memory->facts->map(fn (RelatedFact $related): int => $related->fact->id)->all();
}

describe('recent conversation', function () {
    it('carries the last messages verbatim as turns and puts Josh\'s message last', function () {
        donnaExists();
        fakeEmbeddings();
        $first = Message::factory()->create(['body' => 'Morning.']);
        Message::factory()->replyTo($first)->create(['body' => 'Morning, Josh.']);
        Message::factory()->failed()->create();

        $context = contextFor('What\'s on today?');

        expect($context->turns)->toHaveCount(2)
            ->and(collect($context->turns)->map(fn (Turn $turn): string => $turn->role->value)->all())->toBe(['user', 'assistant'])
            ->and($context->turns[0]->content)->toEndWith('Morning.')
            ->and($context->turns[1]->content)->toBe('Morning, Josh.')
            ->and($context->prompt)->toEndWith('What\'s on today?');
    });

    it('keeps only the newest messages that fit the window, starting on one of Josh\'s', function () {
        config(['donna.context.recent_messages' => 3]);
        donnaExists();
        fakeEmbeddings();
        $old = Message::factory()->create(['body' => 'An old question.', 'embedding' => embedding(0)]);
        Message::factory()->replyTo($old)->create(['body' => 'An old answer.']);
        $recent = Message::factory()->create(['body' => 'A recent question.']);
        Message::factory()->replyTo($recent)->create(['body' => 'A recent answer.']);

        $context = contextFor('And now?');

        // The window of three opens on Donna's old answer, which is dropped.
        expect(collect($context->turns)->pluck('content')->map(fn (string $content): string => str($content)->afterLast("\n")->value())->all())
            ->toBe(['A recent question.', 'A recent answer.'])
            ->and(exchangeIds($context))->toBe([$old->id]);
    });

    it('drops the oldest turns once the window is over its token budget', function () {
        config(['donna.context.recent_token_budget' => 50]);
        donnaExists();
        fakeEmbeddings();
        Message::factory()->create(['body' => str_repeat('long ', 60)]);
        Message::factory()->create(['body' => 'Short and recent.']);

        $context = contextFor('Go on.');

        expect($context->turns)->toHaveCount(1)
            ->and($context->turns[0]->content)->toEndWith('Short and recent.');
    });
});

describe('related past exchanges', function () {
    beforeEach(fn () => config(['donna.context.recent_messages' => 0]));

    it('finds old messages by meaning and by words, each with the reply it got', function () {
        donnaExists();
        fakeEmbeddings(['yarn' => 1]);
        $byMeaning = Message::factory()->create(['body' => 'The wool supplier doubled prices.', 'embedding' => embedding(1)]);
        $itsReply = Message::factory()->replyTo($byMeaning)->create(['body' => 'Then we find another supplier.']);
        $byWords = Message::factory()->create(['body' => 'The yarn vendor called back.']);
        $unrelated = Message::factory()->create(['body' => 'Keyboards are a money pit.', 'embedding' => embedding(2)]);

        $context = contextFor('Should I reorder yarn this month?');

        expect(array_slice(exchangeIds($context), 0, 2))->toEqualCanonicalizing([$byMeaning->id, $byWords->id])
            ->and(array_search($unrelated->id, exchangeIds($context)))->toBeGreaterThan(1)
            ->and($context->memory->exchanges->firstWhere('message.id', $byMeaning->id)->reply->is($itsReply))->toBeTrue()
            ->and($context->prompt)->toContain('The wool supplier doubled prices.', 'Then we find another supplier.');
    });

    it('shows an exchange once when both halves of it match', function () {
        donnaExists();
        fakeEmbeddings(['yarn' => 1]);
        $question = Message::factory()->create(['body' => 'Who is our yarn vendor?', 'embedding' => embedding(1)]);
        Message::factory()->replyTo($question)->create(['body' => 'The yarn vendor is Lark & Loom.', 'embedding' => embedding(1)]);

        $context = contextFor('Email the yarn vendor.');

        expect(exchangeIds($context))->toBe([$question->id]);
    });

    it('drops the weakest memories first when the block is over budget', function () {
        donnaExists();
        fakeEmbeddings(['yarn' => 1]);
        $strong = Message::factory()->create(['body' => 'Yarn margins are thin. '.str_repeat('filler ', 100), 'embedding' => embedding(1)]);
        $weak = Message::factory()->create(['body' => 'Something else entirely. '.str_repeat('filler ', 100), 'embedding' => embedding(2)]);
        config(['donna.context.memory_token_budget' => 250]);

        $context = contextFor('How are yarn margins?');

        expect(exchangeIds($context))->toBe([$strong->id]);
    });
});

describe('facts', function () {
    it('retrieves active facts with what Josh used to think, and never a superseded fact on its own', function () {
        donnaExists();
        fakeEmbeddings(['pricing' => 1]);
        $current = Fact::factory()->create(['statement' => 'Fibermade pricing is $29 a month.', 'embedding' => embedding(1), 'notable_change' => true]);
        $former = Fact::factory()->supersededBy($current)->create(['statement' => 'Fibermade pricing is $19 a month.', 'embedding' => embedding(1)]);

        $context = contextFor('Remind me what we settled on for pricing.');

        expect(factIds($context))->toBe([$current->id])
            ->and($context->memory->facts->first()->fact->predecessors->modelKeys())->toBe([$former->id])
            ->and($context->prompt)->toContain('Fibermade pricing is $29 a month.', 'Fibermade pricing is $19 a month.');
    });

    it('counts each time a fact is put in front of Donna', function () {
        donnaExists();
        fakeEmbeddings();
        travelTo('2026-11-26 15:00:00');
        $fact = Fact::factory()->create(['statement' => 'Josh drinks his coffee black.', 'retrieval_count' => 4]);

        contextFor('Order me a coffee.');

        expect($fact->refresh())
            ->retrieval_count->toBe(5)
            ->last_retrieved_at->toDateTimeString()->toBe('2026-11-26 15:00:00');
    });
});

describe('projects', function () {
    it('boosts memories linked to the project the message relates to, without hiding the rest', function () {
        config(['donna.context.recent_messages' => 0]);
        donnaExists();
        fakeEmbeddings();
        $topic = Topic::factory()->create(['name' => 'Yarn shows']);
        $project = Project::factory()->hasAttached($topic)->create(['name' => 'Fibermade']);

        $elsewhere = Fact::factory()->create(['statement' => 'Booth fees at the festival went up.']);
        $linked = Fact::factory()->hasAttached($topic, relationship: 'topics')->create(['statement' => 'Booth sales doubled at the last show.']);
        $elsewhereMessage = Message::factory()->create(['body' => 'Booth setup takes two hours.']);
        $linkedMessage = Message::factory()->hasAttached($topic)->create(['body' => 'Booth layout needs a rethink.']);

        $context = contextFor('Thinking about the Fibermade booth.');

        expect($context->memory->relatedProjectIds->all())->toBe([$project->id])
            ->and(factIds($context))->toBe([$linked->id, $elsewhere->id])
            ->and(exchangeIds($context))->toBe([$linkedMessage->id, $elsewhereMessage->id]);
    });

    it('lists tracked projects with how long each has been quiet', function () {
        donnaExists();
        fakeEmbeddings();
        travelTo('2026-11-26 15:00:00');
        $quiet = Project::factory()
            ->hasAttached(Topic::factory()->create(['last_discussed_at' => '2026-10-01 12:00:00']))
            ->create(['name' => 'Storyrift']);
        Project::factory()->dropped()->create(['name' => 'Old Thing']);

        $context = contextFor('What am I working on?');

        expect($context->memory->projects->modelKeys())->toBe([$quiet->id])
            ->and($context->memory->projects->first()->last_discussed_at->toDateString())->toBe('2026-10-01')
            ->and($context->profile)->toContain('Storyrift')->not->toContain('Old Thing');
    });
});

describe('people', function () {
    it('brings in anyone the message names, by name or alias, with what Donna knows about them', function () {
        donnaExists();
        fakeEmbeddings(['dentist' => 3]);
        $kristen = Person::factory()->create(['name' => 'Kristen', 'aliases' => ['Kris'], 'relationship' => 'wife']);
        // Not something search would find for this message: it only comes in with her.
        $fact = Fact::factory()->hasAttached($kristen, relationship: 'people')->create(['statement' => 'Kristen runs Bad Frog.']);
        Person::factory()->create(['name' => 'Mark']);

        $context = contextFor('Kris has a dentist appointment Friday.');

        expect($context->memory->people->map(fn (RelatedPerson $related): string => $related->person->name)->all())->toBe(['Kristen'])
            ->and($context->memory->people->first()->facts->modelKeys())->toBe([$fact->id]);
    });

    it('brings in a person a retrieved fact is about even when the message does not name them', function () {
        donnaExists();
        fakeEmbeddings(['vendor' => 1]);
        $sarah = Person::factory()->create(['name' => 'Sarah', 'relationship' => 'yarn vendor']);
        Fact::factory()->hasAttached($sarah, relationship: 'people')->create([
            'statement' => 'Sarah ships wool on Mondays.', 'embedding' => embedding(1),
        ]);

        $context = contextFor('When does the vendor ship?');

        expect($context->memory->people->map(fn (RelatedPerson $related): string => $related->person->name)->all())->toBe(['Sarah']);
    });

    it('brings in the person Josh points at with @, where the name alone would not', function () {
        donnaExists();
        fakeEmbeddings();
        Person::factory()->create(['name' => 'Sarah Lark', 'relationship' => 'yarn vendor']);
        Person::factory()->create(['name' => 'Mark']);

        $context = contextFor('Loop @Sarah in on the invoice.');

        expect($context->memory->people->map(fn (RelatedPerson $related): string => $related->person->name)->all())->toBe(['Sarah Lark'])
            ->and($context->memory->mentions->unknown)->toBe([]);
    });

    it('tells Donna when Josh points at someone she does not know, without inventing them', function () {
        donnaExists();
        fakeEmbeddings();

        $context = contextFor('@Dave is coming Friday. Also bump @vitejs/plugin-react.');

        expect($context->memory->mentions->unknown)->toBe(['Dave'])
            ->and($context->prompt)->toContain('@Dave')
            ->and(Person::where('name', 'Dave')->exists())->toBeFalse();
    });

    it('gives Donna everyone an @ could mean when more than one person fits', function () {
        donnaExists();
        fakeEmbeddings();
        Person::factory()->create(['name' => 'Dave Miller']);
        Person::factory()->create(['name' => 'Dave Chen']);

        $context = contextFor('Call @Dave back.');

        expect($context->memory->people->map(fn (RelatedPerson $related): string => $related->person->name)->all())->toBe(['Dave Miller', 'Dave Chen'])
            ->and(array_keys($context->memory->mentions->ambiguous))->toBe(['Dave'])
            ->and($context->prompt)->toContain('@Dave', 'Dave Miller, Dave Chen');
    });
});

describe('topics', function () {
    it('marks a topic stale once it has been idle for two weeks', function (string $lastDiscussed, bool $stale, int $daysIdle) {
        donnaExists();
        fakeEmbeddings(['keyboard' => 2]);
        travelTo('2026-11-26 15:00:00');
        Topic::factory()->create(['name' => 'Mechanical keyboards', 'embedding' => embedding(2), 'last_discussed_at' => $lastDiscussed]);

        $topic = contextFor('I found another keyboard.')->memory->topics->sole();

        expect($topic)
            ->stale->toBe($stale)
            ->daysIdle->toBe($daysIdle);
    })->with([
        'idle 14 days' => ['2026-11-12 15:00:00', true, 14],
        'idle 13 days' => ['2026-11-13 15:00:00', false, 13],
    ]);

    it('counts how often Josh has come back to a topic, in messages and days', function () {
        config(['donna.context.recent_messages' => 0]);
        donnaExists();
        fakeEmbeddings(['keyboard' => 2]);
        travelTo('2026-11-26 15:00:00');
        $topic = Topic::factory()->create(['name' => 'Mechanical keyboards', 'embedding' => embedding(2)]);

        // 9pm and 11pm Chicago on the 24th, 10am Chicago on the 10th, and one from August.
        foreach (['2026-11-25 03:00:00', '2026-11-25 05:00:00', '2026-11-10 16:00:00', '2026-08-01 12:00:00'] as $at) {
            $said = Message::factory()->hasAttached($topic)->create(['created_at' => $at]);
            Message::factory()->replyTo($said)->hasAttached($topic)->create(['created_at' => $at]);
        }

        $related = contextFor('I found another keyboard.')->memory->topics->sole();

        expect($related->mentions)->toBe([
            7 => ['messages' => 2, 'days' => 1],
            30 => ['messages' => 3, 'days' => 2],
            90 => ['messages' => 3, 'days' => 2],
        ]);
    });

    it('recognises a topic named in the message even without an embedding match', function () {
        donnaExists();
        fakeEmbeddings();
        $topic = Topic::factory()->create(['name' => 'Texas trip', 'embedding' => embedding(7)]);
        Topic::factory()->create(['name' => 'Taxes', 'embedding' => embedding(8)]);

        $context = contextFor('Where were we on the texas trip?');

        expect($context->memory->topics->map(fn (RelatedTopic $related): int => $related->topic->id)->all())->toBe([$topic->id]);
    });
});

it('tells Donna the time in Josh\'s timezone and when he last wrote', function () {
    donnaExists();
    fakeEmbeddings();
    travelTo('2026-11-23 20:00:00');
    Message::factory()->create();
    travelTo('2026-11-26 03:30:00');

    $memory = contextFor('Back.')->memory;

    expect($memory->now->format('Y-m-d H:i T'))->toBe('2026-11-25 21:30 CST')
        ->and($memory->previousMessageAt->toDateTimeString())->toBe('2026-11-23 20:00:00');
});

it('reminds Donna what is still waiting on Josh and which interview areas are open', function () {
    donnaExists();
    fakeEmbeddings();
    $profile = CharacterProfile::factory()->proposed()->create();
    $project = Project::factory()->proposed()->create(['name' => 'Wool Sorter']);
    $open = InterviewArea::factory()->create(['area' => 'Goals']);
    InterviewArea::factory()->create(['area' => 'Work', 'status' => InterviewAreaStatus::Covered]);

    $memory = contextFor('Morning.')->memory;

    expect($memory->proposedProfiles->modelKeys())->toBe([$profile->id])
        ->and($memory->proposedProjects->modelKeys())->toBe([$project->id])
        ->and($memory->interviewAreas->modelKeys())->toBe([$open->id]);
});

it('speaks as the active character profile, not a proposed one', function () {
    donnaExists();
    fakeEmbeddings();
    CharacterProfile::factory()->proposed()->create(['body' => 'You are Donna, but softer.']);

    $context = contextFor('Morning.');

    expect($context->character)->toStartWith('You are Donna.')
        ->not->toContain('You are Donna, but softer.');
});
