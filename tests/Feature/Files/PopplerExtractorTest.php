<?php

use App\Files\Pdf\PopplerExtractor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('reads a PDF with pdftotext, one entry per page, columns kept apart', function () {
    Process::fake([
        '*' => Process::result("Date     Payout     Amount\nNov 1    po_1Qx0    \$100.00\n\fNotes\n\n\f"),
    ]);

    $pages = (new PopplerExtractor)->pages('%PDF-1.7 the bytes');

    expect($pages)->toBe(["Date     Payout     Amount\nNov 1    po_1Qx0    \$100.00", 'Notes']);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['pdftotext', '-layout', '-enc', 'UTF-8', '-', '-']
        && $process->input === '%PDF-1.7 the bytes');
});

it('fails with pdftotext\'s own reason when the PDF can\'t be read', function () {
    Process::fake(['*' => Process::result(errorOutput: "Command Line Error: Incorrect password\nmore", exitCode: 1)]);

    expect(fn () => (new PopplerExtractor)->pages('%PDF-1.7'))->toThrow(RuntimeException::class, 'Command Line Error: Incorrect password');
});
