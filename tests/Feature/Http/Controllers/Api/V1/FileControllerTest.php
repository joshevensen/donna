<?php

use App\Enums\FileExtraction;
use App\Files\Pdf\PdfTextExtractor;
use App\Jobs\IndexFile;
use App\Models\File;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;

beforeEach(fn () => actingAs(User::factory()->create()));

describe('store', function () {
    it('takes in a text file as a working file whose first version is exactly what was uploaded', function (string $name, string $content, string $kind) {
        upload($name, $content)
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.kind', $kind)
            ->assertJsonPath('data.kept', false)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.last_author', 'josh')
            ->assertJsonPath('data.extraction', 'ready');

        $file = File::sole();

        expect($file->body())->toBe($content)
            ->and($file->storage_path)->toBeNull()
            ->and($file->size_bytes)->toBe(strlen($content));
    })->with([
        'markdown' => ['launch-plan.md', "# Launch plan\n\nSix dyers by the 30th.\n", 'markdown'],
        'a .txt note, which is edited like markdown' => ['notes.txt', "Call the venue.\r\nOrder labels.\r\n", 'markdown'],
        'a CSV' => ['payouts.csv', "Date,Amount\nNov 3,812.40\n", 'csv'],
        'JSON, which is text whatever its extension' => ['order.json', '{"total": 42}', 'text'],
        'a tab-separated export, which is queried like a CSV' => ['orders.tsv', "Order\tTotal\n1001\t42.00\n", 'csv'],
        'a file with no extension at all' => ['Dockerfile', "FROM php:8.4\nRUN true\n", 'text'],
    ]);

    it('counts a CSV\'s rows so its card can say how big it is', function () {
        upload('payouts.csv', "Date,Payout,Amount\nNov 3,po_1,812.40\nNov 4,po_2,\"1,220.00\"\n")
            ->assertCreated()
            ->assertJsonPath('data.meta', ['rows' => 2]);
    });

    it('converts a text file that isn\'t UTF-8, as spreadsheet exports often aren\'t', function () {
        upload('export.csv', "Name,City\nJos\xE9,M\xFCnchen\n")->assertCreated();

        expect(File::sole()->body())->toBe("Name,City\nJosé,München\n");
    });

    it('stores a PDF\'s original untouched and keeps its text, page by page, as its one version', function () {
        upload('vendor.pdf', fixtureFile('vendor.pdf'))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'pdf')
            ->assertJsonPath('data.mime', 'application/pdf')
            ->assertJsonPath('data.meta', ['pages' => 2, 'has_text' => true])
            ->assertJsonPath('data.editable', false);

        $file = File::sole();

        expect($file->body())->toBe("Houston Fiber Festival. Booth fee: $450, due January 8.\fTeardown begins Saturday at 5:00 PM.")
            ->and($file->versions()->count())->toBe(1)
            ->and(Storage::get($file->storage_path))->toBe(fixtureFile('vendor.pdf'))
            ->and($file->storage_path)->not->toContain('vendor');
    });

    it('flags a scanned PDF as having no text instead of passing it off as an empty document', function () {
        upload('scan.pdf', fixtureFile('scan.pdf'))
            ->assertCreated()
            ->assertJsonPath('data.extraction', 'ready')
            ->assertJsonPath('data.meta', ['pages' => 1, 'has_text' => false]);
    });

    it('keeps a PDF it couldn\'t read, and says that it couldn\'t', function () {
        Exceptions::fake();
        app()->bind(PdfTextExtractor::class, fn () => new class implements PdfTextExtractor
        {
            public function pages(string $bytes): array
            {
                throw new RuntimeException('Incorrect password');
            }
        });

        upload('locked.pdf', fixtureFile('vendor.pdf'))
            ->assertCreated()
            ->assertJsonPath('data.extraction', 'failed')
            ->assertJsonPath('data.extraction_error', "The PDF's text could not be read. It may be damaged or password-protected.")
            ->assertJsonPath('data.version', null);

        expect(File::sole()->extraction)->toBe(FileExtraction::Failed);
    });

    it('stores an image with its size, and makes a smaller copy of a big photo for the thread and the model', function () {
        $photo = UploadedFile::fake()->image('booth.jpg', 3000, 2000);

        post('/api/v1/files', ['file' => $photo], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.mime', 'image/jpeg')
            ->assertJsonPath('data.version', null)
            ->assertJsonPath('data.meta', ['width' => 3000, 'height' => 2000]);

        $file = File::sole();
        [$width, $height] = getimagesizefromstring(Storage::get($file->previewPath()));

        expect(Storage::get($file->storage_path))->toBe($photo->getContent())
            ->and([$width, $height])->toBe([1568, 1045]);
    });

    it('refuses what Donna can\'t read, with the reason the chip shows, and stores nothing', function (string $name, string $content, string $reason) {
        upload($name, $content)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => $reason]);

        expect(File::count())->toBe(0)
            ->and(Storage::allFiles())->toBe([]);
    })->with([
        'a Word document (a zip inside)' => ['proposal.docx', "PK\x03\x04\x14\x00\x06\x00\x08\x00\x00\x00!\x00".random_bytes(64), 'Unsupported. Text, PDF and images only'],
        'a zip' => ['exports.zip', "PK\x03\x04\x0A\x00\x00\x00\x00\x00".random_bytes(64), 'Unsupported. Text, PDF and images only'],
        'binary under a text name' => ['notes.md', "\x00\x01\x02\x03".random_bytes(64), 'Unsupported. Text, PDF and images only'],
        'text calling itself a PDF' => ['invoice.pdf', 'Total due: $40', 'Unsupported. The file isn\'t what its name says'],
        'a PDF calling itself a photo' => ['scan.png', fixtureFile('vendor.pdf'), 'Unsupported. The file isn\'t what its name says'],
        'an empty file' => ['empty.md', '', 'Unsupported. The file is empty'],
        'an iPhone photo that wasn\'t converted' => ['IMG_2041.heic', "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".random_bytes(64), 'Unsupported. Send the photo as a JPEG, not HEIC'],
    ]);

    it('refuses a file over 10 MB', function () {
        post('/api/v1/files', ['file' => UploadedFile::fake()->create('big.csv', 10241)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Too large. Max 10 MB']);

        expect(File::count())->toBe(0);
    });

    it('never uses the name as a path', function () {
        upload('../../etc/passwd.md', '# Notes')->assertCreated()->assertJsonPath('data.name', 'passwd.md');
    });

    it('stops taking uploads after thirty in a minute', function () {
        foreach (range(1, 30) as $sent) {
            upload("note-{$sent}.md", '# Note')->assertCreated();
        }

        upload('one-more.md', '# Note')->assertTooManyRequests();
    });
});

describe('index', function () {
    it('lists kept files first, then working files used in the last thirty days, and nothing deleted or never sent', function () {
        $message = Message::factory()->create();
        $recent = File::factory()->markdown()->on($message)->create(['name' => 'recent.md', 'updated_at' => now()->subDays(2)]);
        $kept = File::factory()->markdown()->kept()->create(['name' => 'kept.md', 'updated_at' => now()->subDays(200)]);
        File::factory()->markdown()->on($message)->create(['name' => 'old.md', 'updated_at' => now()->subDays(40)]);
        File::factory()->markdown()->on($message)->create(['name' => 'deleted.md'])->delete();
        File::factory()->markdown()->create(['name' => 'never-sent.md']);

        getJson('/api/v1/files')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$kept->id, $recent->id])
            ->assertJsonPath('data.0.kept', true)
            ->assertJsonMissingPath('data.0.body');
    });

    it('finds an older working file by name', function () {
        $old = File::factory()->markdown()->on(Message::factory()->create())->create(['name' => 'Houston-logistics.md', 'updated_at' => now()->subDays(90)]);
        File::factory()->markdown()->kept()->create(['name' => 'launch-plan.md']);

        getJson('/api/v1/files?q=houston')->assertOk()->assertJsonPath('data.*.id', [$old->id]);
    });
});

describe('show', function () {
    it('returns a text file with its current text and version', function () {
        $file = File::factory()->markdown("# Plan\n\nShip it.\n")->create();

        getJson("/api/v1/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.body', "# Plan\n\nShip it.\n")
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.truncated', false)
            ->assertJsonPath('data.table', null);
    });

    it('says how long a text file is and what it is linked to, for the viewer\'s details', function () {
        $file = File::factory()->markdown("# Plan\n\nShip it.\n")->create();
        $file->projects()->attach(Project::factory()->create(['name' => 'Fibermade']));

        getJson("/api/v1/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.meta', ['lines' => 4])
            ->assertJsonPath('data.subjects', [['type' => 'project', 'name' => 'Fibermade']]);
    });

    it('returns a CSV as the first rows of a table, parsed, with how many there are in all', function () {
        config(['donna.files.viewer_rows' => 2]);
        $file = File::factory()->csv("Date,Note\nNov 3,\"Paid, late\"\nNov 4,Paid\nNov 5,Pending\n")->create();

        getJson("/api/v1/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.table', [
                'columns' => ['Date', 'Note'],
                'rows' => [['Nov 3', 'Paid, late'], ['Nov 4', 'Paid']],
                'total_rows' => 3,
            ]);
    });

    it('answers 404 for a deleted file', function () {
        $file = File::factory()->markdown()->create();
        $file->delete();

        getJson("/api/v1/files/{$file->id}")->assertNotFound();
    });
});

describe('update', function () {
    it('keeps a file with the switch, queues its indexing, and stops keeping it again', function () {
        Queue::fake([IndexFile::class]);
        $file = File::factory()->markdown()->create();

        patchJson("/api/v1/files/{$file->id}", ['kept' => true])->assertOk()->assertJsonPath('data.kept', true);

        Queue::assertPushed(IndexFile::class, fn (IndexFile $job): bool => $job->fileId === $file->id);

        patchJson("/api/v1/files/{$file->id}", ['kept' => false])->assertOk()->assertJsonPath('data.kept', false);

        expect($file->refresh()->kept_at)->toBeNull();
    });

    it('refuses to keep an image: there is no text in one to find it by', function () {
        $file = File::factory()->image()->create();

        patchJson("/api/v1/files/{$file->id}", ['kept' => true])->assertUnprocessable();

        expect($file->refresh()->isKept())->toBeFalse();
    });

    it('renames a file, and refuses a name with a path in it', function () {
        $file = File::factory()->markdown()->create(['name' => 'plan.md']);

        patchJson("/api/v1/files/{$file->id}", ['name' => 'launch-plan.md'])->assertOk()->assertJsonPath('data.name', 'launch-plan.md');
        patchJson("/api/v1/files/{$file->id}", ['name' => '../plan.md'])->assertUnprocessable();

        expect($file->refresh()->name)->toBe('launch-plan.md');
    });
});

describe('content', function () {
    it('serves an image inline as the type it was found to be, to a signed-in session only', function () {
        $file = File::factory()->image()->create(['name' => 'booth.png']);

        $response = getJson("/api/v1/files/{$file->id}/content")->assertOk();

        expect($response->streamedContent())->toBe('png bytes')
            ->and($response->headers->get('Content-Type'))->toBe('image/png')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox")
            ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename=booth.png');
    });

    it('serves the smaller copy of an image when asked for the preview', function () {
        $file = File::factory()->image()->create();
        Storage::put('files/small.preview.jpg', 'small jpeg');
        $file->update(['meta' => [...$file->meta, 'preview_path' => 'files/small.preview.jpg']]);

        $response = getJson("/api/v1/files/{$file->id}/content?variant=preview")->assertOk();

        expect($response->streamedContent())->toBe('small jpeg')
            ->and($response->headers->get('Content-Type'))->toBe('image/jpeg');
    });

    it('serves a text file only as a plain-text download, never as a page', function () {
        $file = File::factory()->text('<script>alert(1)</script>')->create(['name' => 'page.html']);

        $response = getJson("/api/v1/files/{$file->id}/content")->assertOk();

        expect($response->streamedContent())->toBe('<script>alert(1)</script>')
            ->and($response->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename=page.html');
    });
});
