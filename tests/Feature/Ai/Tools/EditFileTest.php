<?php

use App\Ai\Agents\Donna;
use App\Enums\MessageAuthor;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

const PLAN = "# Launch plan\n\nFibermade Co launches Jan 25.\n\n## Pricing\n\nFibermade Co charges $29.\nFibermade Co bills monthly.\n\n## Dyers\n\n- Studio one\n- Studio two\n";

beforeEach(function () {
    donnaExists();
    fakeEmbeddings();
});

it('applies each kind of edit in code, as a new version that says what changed, with a card on her reply', function (array $edit, string $summary, string $body) {
    $file = File::factory()->markdown(PLAN)->create(['name' => 'launch-plan.md']);

    [$result] = donnaCalls([['edit_file', ['file' => 'launch-plan.md', ...$edit]]]);

    $version = $file->refresh()->currentVersion;

    expect($result)->toBe("{$summary}. launch-plan.md is now at version 2.")
        ->and($version)
        ->number->toBe(2)
        ->body->toBe($body)
        ->author->toBe(MessageAuthor::Donna)
        ->summary->toBe($summary)
        ->message_id->toBe(donnasReply()->id)
        ->and($file->size_bytes)->toBe(strlen($body));

    $this->actingAs(User::first())
        ->getJson('/api/v1/messages/'.donnasReply()->id)
        ->assertJsonPath('data.files.0.id', $file->id)
        ->assertJsonPath('data.files.0.role', 'edited')
        ->assertJsonPath('data.files.0.note', $summary);
})->with([
    'replace everywhere' => [
        ['operation' => 'replace', 'find' => 'Fibermade Co', 'replace' => 'Fibermade', 'all' => true],
        'Replaced 3 matches of "Fibermade Co"',
        str_replace('Fibermade Co', 'Fibermade', PLAN),
    ],
    'replace the one match' => [
        ['operation' => 'replace', 'find' => 'Jan 25', 'replace' => 'Feb 1'],
        'Replaced 1 match of "Jan 25"',
        str_replace('Jan 25', 'Feb 1', PLAN),
    ],
    'rewrite one section and nothing else' => [
        ['operation' => 'replace_section', 'heading' => 'pricing', 'new_text' => "## Pricing\n\nOne flat plan: $29 a month."],
        'Rewrote the "Pricing" section',
        "# Launch plan\n\nFibermade Co launches Jan 25.\n\n## Pricing\n\nOne flat plan: $29 a month.\n\n## Dyers\n\n- Studio one\n- Studio two\n",
    ],
    'append' => [
        ['operation' => 'append', 'text' => "\n## Open questions\n\n- Who migrates first?"],
        'Appended 4 lines',
        PLAN."\n## Open questions\n\n- Who migrates first?\n",
    ],
    'delete a line by number' => [
        ['operation' => 'delete_lines', 'from_line' => 12],
        'Deleted line 12',
        str_replace("- Studio one\n", '', PLAN),
    ],
    'delete a line by its text' => [
        ['operation' => 'delete_lines', 'content' => '- Studio two'],
        'Deleted 1 line',
        str_replace("- Studio two\n", '', PLAN),
    ],
]);

it('changes nothing and says why when an edit can\'t be applied as asked', function (array $edit, string $reason) {
    $file = File::factory()->markdown(PLAN)->create(['name' => 'launch-plan.md']);

    [$result] = donnaCalls([['edit_file', ['file' => 'launch-plan.md', ...$edit]]]);

    expect($result)->toContain($reason)
        ->and($file->versions()->count())->toBe(1)
        ->and($file->refresh()->body())->toBe(PLAN);
})->with([
    'the text to replace isn\'t there' => [['operation' => 'replace', 'find' => 'Fibermade Inc', 'replace' => 'Fibermade'], 'No match for "Fibermade Inc". Nothing changed.'],
    'the text to replace is there three times' => [['operation' => 'replace', 'find' => 'Fibermade Co', 'replace' => 'Fibermade'], '"Fibermade Co" appears 3 times. Nothing changed.'],
    'there is no such heading' => [['operation' => 'replace_section', 'heading' => 'Returns', 'new_text' => 'x'], 'There is no heading "Returns". Nothing changed. The headings are: "Launch plan", "Launch plan › Pricing", "Launch plan › Dyers".'],
    'the lines aren\'t in the file' => [['operation' => 'delete_lines', 'from_line' => 40, 'to_line' => 41], 'The file has 14 lines; 40 to 41 isn\'t a range in it. Nothing changed.'],
]);

it('edits markdown only: a CSV is read, not changed', function () {
    $file = File::factory()->csv("Date,Amount\nNov 3,812.40\n")->create(['name' => 'payouts.csv']);

    [$result] = donnaCalls([['edit_file', ['file' => 'payouts.csv', 'operation' => 'append', 'text' => 'Nov 4,220.00']]]);

    expect($result)->toBe('payouts.csv is a CSV file. Only markdown files (.md, .txt) can be edited; the others are read.')
        ->and($file->versions()->count())->toBe(1);
});

it('keeps the file\'s own line endings when it edits one that came from Windows', function () {
    $file = File::factory()->markdown("Call the venue.\r\nOrder labels.\r\n")->create(['name' => 'notes.txt']);

    donnaCalls([['edit_file', ['file' => 'notes.txt', 'operation' => 'append', 'text' => "Book the van.\nPack samples."]]]);

    expect($file->refresh()->body())->toBe("Call the venue.\r\nOrder labels.\r\nBook the van.\r\nPack samples.\r\n");
});

// A reply that fails partway is written again from the start, tool calls and all.
it('does not append a second time when the reply it was made in runs again', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    $file = File::factory()->markdown("# Notes\n")->create(['name' => 'notes.md']);
    $reply = pendingReplyTo('Add the venue to my notes.');
    $append = new ToolCall('call_1', 'edit_file', ['file' => 'notes.md', 'operation' => 'append', 'text' => '- Venue: east dock']);

    // The first attempt dies after the edit; the queue would run the job again.
    Donna::fake([$append, '']);
    expect(fn () => app()->call([new GenerateReply($reply), 'handle']))->toThrow(RuntimeException::class, 'empty reply');

    Donna::fake([$append, 'Added.']);
    GenerateReply::dispatchSync($reply->refresh());

    expect($file->refresh()->body())->toBe("# Notes\n- Venue: east dock\n")
        ->and($file->versions()->count())->toBe(2)
        ->and($reply->refresh()->body)->toBe('Added.');
});

it('never touches version 1, however many edits and reverts follow', function () {
    $file = File::factory()->markdown(PLAN)->create(['name' => 'launch-plan.md']);

    donnaCalls([
        ['edit_file', ['file' => 'launch-plan.md', 'operation' => 'replace', 'find' => 'Fibermade Co', 'replace' => 'Fibermade', 'all' => true]],
        ['edit_file', ['file' => 'launch-plan.md', 'operation' => 'append', 'text' => 'More.']],
        ['revert_file', ['file' => 'launch-plan.md', 'version' => 2]],
        ['edit_file', ['file' => 'launch-plan.md', 'operation' => 'delete_lines', 'from_line' => 1]],
    ]);

    $original = $file->versions()->where('number', 1)->sole();

    expect($file->versions()->count())->toBe(5)
        ->and($original)
        ->body->toBe(PLAN)
        ->author->toBe(MessageAuthor::Josh)
        ->summary->toBe('Uploaded');
});

// The model rarely words an edit the same way twice, so a retried reply is told what it already did.
it('tells a reply that is being written again which changes it has already made', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    $file = File::factory()->markdown("# Notes\n")->create(['name' => 'notes.md']);
    $reply = pendingReplyTo('Add the venue to my notes.');

    Donna::fake([new ToolCall('call_1', 'edit_file', ['file' => 'notes.md', 'operation' => 'append', 'text' => '- Venue: east dock']), '']);
    expect(fn () => app()->call([new GenerateReply($reply), 'handle']))->toThrow(RuntimeException::class);

    Donna::fake(['Added the venue.']);
    GenerateReply::dispatchSync($reply->refresh());

    Donna::assertPrompted(fn ($prompt): bool => $prompt->contains('An earlier attempt at this reply was cut short')
        && $prompt->contains("- notes.md (file #{$file->id}): Appended 1 line, now version 2"));

    expect($file->versions()->count())->toBe(2);
});

it('lets her make the same edit twice in one go when she means to', function () {
    $file = File::factory()->markdown("one\ntwo\nthree\n")->create(['name' => 'notes.md']);

    donnaCalls([
        ['edit_file', ['file' => 'notes.md', 'operation' => 'delete_lines', 'from_line' => 1]],
        ['edit_file', ['file' => 'notes.md', 'operation' => 'delete_lines', 'from_line' => 1]],
    ], said: 'Delete the first two lines.');

    expect($file->refresh()->body())->toBe("three\n");
});

it('leaves a form feed or a Unicode line separator inside a line alone when it edits around it', function () {
    $file = File::factory()->markdown("first\fstill first\nsecond\u{2028}still second\nthird\n")->create(['name' => 'notes.md']);

    donnaCalls([['edit_file', ['file' => 'notes.md', 'operation' => 'delete_lines', 'from_line' => 3]]]);

    expect($file->refresh()->body())->toBe("first\fstill first\nsecond\u{2028}still second\n");
});
