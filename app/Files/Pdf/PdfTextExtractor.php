<?php

namespace App\Files\Pdf;

use Throwable;

/**
 * Reads the text out of a PDF, page by page. Plain code: no model is asked.
 */
interface PdfTextExtractor
{
    /**
     * @return list<string> The text of each page, in order. A page with no text is an empty string.
     *
     * @throws Throwable When the PDF can't be read at all.
     */
    public function pages(string $bytes): array;
}
