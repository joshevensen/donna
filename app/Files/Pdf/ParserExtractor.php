<?php

namespace App\Files\Pdf;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * A pure-PHP reader, for a machine without `pdftotext`. It finds the words
 * but runs the cells of a table together, so it is the fallback.
 */
class ParserExtractor implements PdfTextExtractor
{
    public function pages(string $bytes): array
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        $document = (new Parser([], $config))->parseContent($bytes);

        return array_values(array_map(
            fn ($page): string => trim($page->getText()),
            $document->getPages(),
        ));
    }
}
