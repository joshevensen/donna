<?php

use App\Ai\Agents\Donna;
use App\Enums\FileKind;
use App\Enums\MessageAuthor;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\IndexFile;
use App\Jobs\TagTopics;
use App\Models\File;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    donnaExists();
    fakeEmbeddings();
});

/**
 * The file cards on the reply Donna just wrote, as the web app gets them.
 *
 * @return list<array<string, mixed>>
 */
function cardsOnReply(): array
{
    actingAs(User::first());

    return getJson('/api/v1/messages/'.donnasReply()->id)->json('data.files');
}

describe('create_file', function () {
    it('writes a new markdown file as a working file, with a card on her reply', function () {
        [$result] = donnaCalls([['create_file', ['name' => 'Launch plan', 'content' => "# Launch plan\n\nSix dyers by the 30th.\n"]]], said: 'Write that up as a plan.');

        $file = File::sole();

        expect($result)->toBe("Created file #{$file->id} \"Launch plan.md\". A card for it sits under your reply.")
            ->and($file)
            ->kind->toBe(FileKind::Markdown)
            ->isKept()->toBeFalse()
            ->and($file->currentVersion)
            ->number->toBe(1)
            ->body->toBe("# Launch plan\n\nSix dyers by the 30th.\n")
            ->author->toBe(MessageAuthor::Donna)
            ->and(cardsOnReply())->toMatchArray([['id' => $file->id, 'name' => 'Launch plan.md', 'kind' => 'markdown', 'role' => 'created', 'kept' => false, 'deleted' => false, 'size_bytes' => 38, 'meta' => [], 'note' => null]]);
    });

    it('creates the file once when the reply it was written in runs again', function () {
        Queue::fake([EmbedMessage::class, TagTopics::class]);
        $reply = pendingReplyTo('Write that up.');
        $create = new ToolCall('call_1', 'create_file', ['name' => 'plan.md', 'content' => '# Plan']);

        Donna::fake([$create, '']);
        expect(fn () => app()->call([new GenerateReply($reply), 'handle']))->toThrow(RuntimeException::class);

        Donna::fake([$create, 'Done.']);
        GenerateReply::dispatchSync($reply->refresh());

        expect(File::count())->toBe(1);
    });
});

describe('read_file', function () {
    it('returns part of a file with its line numbers: a section by heading, or a range', function () {
        File::factory()->markdown("# Plan\n\nIntro.\n\n## Pricing\n\n$29 a month.\n\n## Dyers\n\n- Studio one\n")->create(['name' => 'plan.md']);

        [$section, $range] = donnaCalls([
            ['read_file', ['file' => 'plan.md', 'heading' => 'Pricing']],
            ['read_file', ['file' => 'plan.md', 'from_line' => 9, 'lines' => 3]],
        ]);

        expect($section)->toEndWith("lines 5–8 of 12:\n5| ## Pricing\n6| \n7| $29 a month.\n8| ")
            ->and($range)->toEndWith("lines 9–11 of 12:\n 9| ## Dyers\n10| \n11| - Studio one");
    });

    it('gives a long file a piece at a time and says where the next piece starts', function () {
        config(['donna.files.read_tokens' => 20]);
        File::factory()->text(implode("\n", array_map(fn (int $line): string => "line number {$line}", range(1, 40))))->create(['name' => 'log.txt']);

        [$result] = donnaCalls([['read_file', ['file' => 'log.txt']]]);

        expect($result)->toContain('lines 1–5 of 40. There is more: ask again with from_line 6:')
            ->and($result)->not->toContain('line number 6');
    });
});

describe('query_csv', function () {
    it('answers a question about a CSV in code, and describes the file when asked nothing', function () {
        File::factory()->csv("Date,Payout,Amount,Status\nNov 3,po_1,\"$1,030.00\",In transit\nNov 4,po_2,$640.00,In transit\nNov 5,po_3,$470.00,In transit\nNov 6,po_4,$99.00,Paid\n")->create(['name' => 'payouts.csv']);

        [$total, $plain] = donnaCalls([
            ['query_csv', ['file' => 'payouts.csv', 'where' => [['column' => 'status', 'op' => '=', 'value' => 'in transit']], 'aggregate' => [['fn' => 'count'], ['fn' => 'sum', 'column' => 'Amount']]]],
            ['query_csv', ['file' => 'payouts.csv']],
        ], said: 'Which payouts are late?');

        expect($total)->toBe("payouts.csv: 4 rows. Columns: Date, Payout, Amount, Status.\n3 rows match the filter.\ncount,sum(Amount)\n3,2140.00")
            ->and($plain)->toStartWith("payouts.csv: 4 rows. Columns: Date, Payout, Amount, Status.\nDate,Payout,Amount,Status\nNov 3,po_1,\"$1,030.00\",In transit");
    });

    it('names the columns when asked about one that isn\'t there', function () {
        File::factory()->csv("Date,Amount\nNov 3,812.40\n")->create(['name' => 'payouts.csv']);

        [$result] = donnaCalls([['query_csv', ['file' => 'payouts.csv', 'aggregate' => [['fn' => 'sum', 'column' => 'Total']]]]]);

        expect($result)->toBe('There is no column "Total". The columns are: Date, Amount.');
    });
});

describe('keep_file', function () {
    it('keeps a file on her own say-so, indexes it, and shows the keep on her reply', function () {
        $file = File::factory()->markdown("# Vendor notes\n\nLoad-in is at 7.\n")->on(Message::factory()->create())->create(['name' => 'vendor-notes.md']);

        [$result] = donnaCalls([['keep_file', ['file' => (string) $file->id, 'keep' => true]]]);

        expect($result)->toStartWith('Kept vendor-notes.md.')
            ->and($file->refresh()->isKept())->toBeTrue()
            ->and($file->chunks()->whereNotNull('embedding')->count())->toBe(1)
            ->and(cardsOnReply()[0])->toMatchArray(['id' => $file->id, 'role' => 'opened', 'kept' => true]);
    });

    it('stops keeping a file, which leaves the file and takes away its chunks', function () {
        $file = File::factory()->markdown("# Vendor notes\n\nLoad-in is at 7.\n")->kept()->create(['name' => 'vendor-notes.md']);
        IndexFile::dispatchSync($file->id);

        donnaCalls([['keep_file', ['file' => 'vendor-notes.md', 'keep' => false]]], said: 'Forget that one.');

        expect($file->refresh())
            ->isKept()->toBeFalse()
            ->trashed()->toBeFalse()
            ->and($file->chunks()->count())->toBe(0)
            ->and($file->body())->toBe("# Vendor notes\n\nLoad-in is at 7.\n");
    });

    it('warns her that a kept scan has no words to be found by', function () {
        File::factory()->pdf([], scannedPages: 2)->create(['name' => 'receipt-scan.pdf']);

        [$result] = donnaCalls([['keep_file', ['file' => 'receipt-scan.pdf', 'keep' => true]]]);

        expect($result)->toContain('It has no text, so you won\'t find it by what it says');
    });

    it('does not keep images: there is nothing in them to search', function () {
        $file = File::factory()->image()->create(['name' => 'booth.png']);

        [$result] = donnaCalls([['keep_file', ['file' => 'booth.png', 'keep' => true]]]);

        expect($result)->toContain('Images aren\'t kept')
            ->and($file->refresh()->isKept())->toBeFalse();
    });
});

describe('link_file', function () {
    it('links a file to the people, projects and topics it is about, by name, and unlinks them', function () {
        $file = File::factory()->pdf()->create(['name' => 'houston-logistics.pdf']);
        $anita = Person::factory()->create(['name' => 'Anita', 'aliases' => ['Ani']]);
        $project = Project::factory()->create(['name' => 'Fibermade']);

        [$linked, $unlinked] = donnaCalls([
            ['link_file', ['file' => 'houston-logistics.pdf', 'subjects' => [
                ['type' => 'person', 'name' => 'ani'],
                ['type' => 'project', 'name' => 'Fibermade'],
                ['type' => 'topic', 'name' => 'Houston festival'],
                ['type' => 'project', 'name' => 'Untracked'],
            ]]],
            ['link_file', ['file' => 'houston-logistics.pdf', 'unlink' => true, 'subjects' => [['type' => 'person', 'name' => 'Anita']]]],
        ]);

        expect($linked)->toBe('houston-logistics.pdf is linked to Anita, Fibermade, Houston festival. Not linked to Untracked: no such project is tracked.')
            ->and($unlinked)->toBe('houston-logistics.pdf is no longer linked to Anita.')
            ->and($file->people()->count())->toBe(0)
            ->and($file->projects->modelKeys())->toBe([$project->id])
            ->and($file->topics->modelKeys())->toBe([Topic::where('name', 'Houston festival')->sole()->id])
            ->and($anita->exists)->toBeTrue();
    });
    it('says so when asked to remove a link that was never there', function () {
        File::factory()->pdf()->create(['name' => 'houston-logistics.pdf']);
        Person::factory()->create(['name' => 'Anita']);

        [$result] = donnaCalls([['link_file', ['file' => 'houston-logistics.pdf', 'unlink' => true, 'subjects' => [['type' => 'person', 'name' => 'Anita']]]]]);

        expect($result)->toBe('houston-logistics.pdf wasn\'t linked to any of those.');
    });
});

describe('delete_file and restore_file', function () {
    it('deletes a file softly, taking its chunks, and brings it back as it was, re-indexed', function () {
        $file = File::factory()->markdown("# Vendor notes\n\nLoad-in is at 7.\n")->kept()->create(['name' => 'vendor-notes.md']);
        IndexFile::dispatchSync($file->id);

        [$deleted] = donnaCalls([['delete_file', ['file' => 'vendor-notes.md']]], said: 'Delete the vendor notes.');

        expect($deleted)->toStartWith("Deleted \"vendor-notes.md\" (file #{$file->id}).")
            ->and($file->refresh()->trashed())->toBeTrue()
            ->and($file->chunks()->count())->toBe(0)
            ->and(cardsOnReply()[0])->toMatchArray(['id' => $file->id, 'deleted' => true]);

        [$restored] = donnaCalls([['restore_file', ['file' => 'vendor-notes.md']]], said: 'Actually, bring it back.');

        expect($restored)->toBe("\"vendor-notes.md\" (file #{$file->id}) is back, and kept as before.")
            ->and($file->refresh()->trashed())->toBeFalse()
            ->and($file->chunks()->count())->toBe(1)
            ->and($file->chunks()->sole()->embedding)->not->toBeNull();
    });
});

describe('rename_file', function () {
    it('renames a file, found by its number', function () {
        $file = File::factory()->markdown()->create(['name' => 'plan.md']);

        [$result] = donnaCalls([['rename_file', ['file' => "#{$file->id}", 'name' => 'launch-plan.md']]]);

        expect($result)->toBe("Renamed \"plan.md\" to \"launch-plan.md\" (still file #{$file->id}).")
            ->and($file->refresh()->name)->toBe('launch-plan.md');
    });
});

describe('list_files', function () {
    it('lists what there is, kept first, and puts the files on her reply as cards', function () {
        $message = Message::factory()->create();
        $working = File::factory()->csv()->on($message)->create(['name' => 'payouts.csv']);
        $kept = File::factory()->markdown()->kept()->create(['name' => 'launch-plan.md']);
        File::factory()->markdown()->create(['name' => 'uploaded-but-never-sent.md']);

        [$result] = donnaCalls([['list_files', []]], said: 'Show me my files.');

        expect($result)->toContain("- file #{$kept->id} \"launch-plan.md\" — markdown")
            ->and(strpos($result, 'launch-plan.md'))->toBeLessThan(strpos($result, 'payouts.csv'))
            ->and($result)->not->toContain('never-sent')
            ->and(collect(cardsOnReply())->pluck('role', 'id')->all())->toBe([$working->id => 'opened', $kept->id => 'opened']);
    });

    it('finds a file by part of its name, and says so when nothing matches', function () {
        $plan = File::factory()->markdown()->kept()->create(['name' => 'Fibermade-launch-plan.md']);
        File::factory()->markdown()->kept()->create(['name' => 'pricing.md']);

        [$found, $none] = donnaCalls([['list_files', ['q' => 'launch']], ['list_files', ['q' => 'houston']]], said: 'Open the launch plan.');

        expect($found)->toContain('Fibermade-launch-plan.md')->not->toContain('pricing.md')
            ->and($none)->toBe('No file\'s name contains "houston".')
            ->and(collect(cardsOnReply())->pluck('id')->all())->toBe([$plan->id]);
    });
});

it('tells her plainly when a file she names isn\'t there, is deleted, or could be more than one', function () {
    File::factory()->markdown()->create(['name' => 'plan-a.md']);
    File::factory()->markdown()->create(['name' => 'plan-b.md']);
    $gone = File::factory()->markdown()->create(['name' => 'old.md']);
    $gone->delete();

    [$missing, $deleted, $ambiguous] = donnaCalls([
        ['read_file', ['file' => 'budget.md']],
        ['read_file', ['file' => (string) $gone->id]],
        ['read_file', ['file' => 'plan']],
    ]);

    expect($missing)->toBe('There is no file called "budget.md". Use list_files to see what there is.')
        ->and($deleted)->toBe("File #{$gone->id} was deleted. restore_file brings it back, if Josh asks.")
        ->and($ambiguous)->toStartWith('More than one file matches "plan":');
});
