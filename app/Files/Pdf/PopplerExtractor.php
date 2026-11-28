<?php

namespace App\Files\Pdf;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * `pdftotext` from poppler-utils. It keeps the columns of a table apart,
 * which matters for statements and exports, so it is used whenever the
 * server has it.
 */
class PopplerExtractor implements PdfTextExtractor
{
    public const string BINARY = 'pdftotext';

    public static function isInstalled(): bool
    {
        return (new ExecutableFinder)->find(self::BINARY) !== null;
    }

    public function pages(string $bytes): array
    {
        $result = Process::timeout(config('donna.files.pdftotext_seconds'))
            ->input($bytes)
            ->run([self::BINARY, '-layout', '-enc', 'UTF-8', '-', '-']);

        if ($result->failed()) {
            throw new RuntimeException(str($result->errorOutput())->before("\n")->trim()->value() ?: 'pdftotext could not read the file.');
        }

        // Every page ends with a form feed, so the last piece is always empty.
        $pages = explode("\f", $result->output());
        array_pop($pages);

        return array_map(fn (string $page): string => rtrim($page), $pages);
    }
}
