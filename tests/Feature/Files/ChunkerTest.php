<?php

use App\Enums\FileKind;
use App\Files\Chunker;

/*
 | Archive Memory finds a file by its parts, so where a file is cut decides
 | what can be found. Always at a logical boundary, never mid-thought.
 */

beforeEach(fn () => config(['donna.files.chunk_tokens' => 20]));

it('cuts markdown at every heading, each part carrying its heading path instead of the heading line', function () {
    $chunks = (new Chunker)->chunk(FileKind::Markdown, "Opening line.\n\n# Plan\n\nLaunch Jan 25.\n\n## Pricing\n\nFlat plan.\n\n```\n# not a heading\n```\n\n### Wholesale\n\nLater.\n\n## Empty\n\n## Dyers\n\n- Studio one\n");

    expect($chunks)->toBe([
        ['heading_path' => null, 'body' => 'Opening line.'],
        ['heading_path' => 'Plan', 'body' => 'Launch Jan 25.'],
        ['heading_path' => 'Plan › Pricing', 'body' => "Flat plan.\n\n```\n# not a heading\n```"],
        ['heading_path' => 'Plan › Pricing › Wholesale', 'body' => 'Later.'],
        ['heading_path' => 'Plan › Dyers', 'body' => '- Studio one'],
    ]);
});

it('splits a long section between paragraphs, keeping its heading path on every piece', function () {
    $paragraph = fn (string $word): string => trim(str_repeat("{$word} ", 10));

    $chunks = (new Chunker)->chunk(FileKind::Markdown, "## Returns\n\n{$paragraph('alpha')}\n\n{$paragraph('beta')}\n\n{$paragraph('gamma')}\n");

    expect($chunks)->toBe([
        ['heading_path' => 'Returns', 'body' => $paragraph('alpha')],
        ['heading_path' => 'Returns', 'body' => $paragraph('beta')],
        ['heading_path' => 'Returns', 'body' => $paragraph('gamma')],
    ]);
});

it('groups a CSV\'s rows with the header on every group, so each can be read alone', function () {
    $chunks = (new Chunker)->chunk(FileKind::Csv, "Date,Payout,Note\n2026-11-03,po_1,\"Paid, late\"\n2026-11-04,po_2,Paid\n2026-11-05,po_3,Paid\n2026-11-06,po_4,Pending\n");

    expect($chunks)->toBe([
        ['heading_path' => 'Rows 1–2', 'body' => "Date,Payout,Note\n2026-11-03,po_1,\"Paid, late\"\n2026-11-04,po_2,Paid"],
        ['heading_path' => 'Rows 3–4', 'body' => "Date,Payout,Note\n2026-11-05,po_3,Paid\n2026-11-06,po_4,Pending"],
    ]);
});

it('indexes only the first rows of a very long CSV, plus one chunk that says what the whole file is', function () {
    config(['donna.files.csv_chunk_rows' => 2, 'donna.files.chunk_tokens' => 800]);

    $chunks = (new Chunker)->chunk(FileKind::Csv, "Date,Amount\nNov 1,1\nNov 2,2\nNov 3,3\nNov 4,4\n");

    expect($chunks)->toBe([
        ['heading_path' => 'Rows 1–2', 'body' => "Date,Amount\nNov 1,1\nNov 2,2"],
        ['heading_path' => 'Summary', 'body' => "Columns: Date, Amount\n4 rows. Only the first 2 are indexed here.\nFirst rows:\nDate,Amount\nNov 1,1\nNov 2,2"],
    ]);
});

it('cuts a PDF by page, skipping pages with nothing on them', function () {
    $chunks = (new Chunker)->chunk(FileKind::Pdf, "Booth fee: $450.\f\fTeardown at 5.");

    expect($chunks)->toBe([
        ['heading_path' => 'Page 1', 'body' => 'Booth fee: $450.'],
        ['heading_path' => 'Page 3', 'body' => 'Teardown at 5.'],
    ]);
});

it('never makes a chunk bigger than the budget, even from one enormous line', function () {
    $chunks = (new Chunker)->chunk(FileKind::Text, str_repeat('x', 500));

    expect($chunks)->toHaveCount(7)
        ->and(collect($chunks)->every(fn (array $chunk): bool => mb_strlen($chunk['body']) <= 80))->toBeTrue();
});
