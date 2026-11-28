<?php

namespace App\Files;

/**
 * Tells text from binary by content and brings it to UTF-8, which is what
 * the database and the model both need.
 */
class Encoding
{
    /**
     * The content as UTF-8 text, or null when it isn't text.
     *
     * UTF-8 passes as it is (less a byte-order mark). UTF-16 with a
     * byte-order mark is converted. Anything else without binary bytes is
     * read as Windows-1252, which is what spreadsheet exports that aren't
     * UTF-8 almost always are.
     */
    public static function toUtf8(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        } elseif (str_starts_with($bytes, "\xFF\xFE")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($bytes, "\xFE\xFF")) {
            $bytes = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_contains($bytes, "\0")) {
            return null;
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }

        // Control characters no text file holds. Tab, the line endings, form feed and escape are fine.
        if (preg_match('/[\x01-\x08\x0E-\x1A\x1C-\x1F\x7F]/', $bytes) === 1) {
            return null;
        }

        return $bytes;
    }
}
