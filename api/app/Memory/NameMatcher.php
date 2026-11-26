<?php

namespace App\Memory;

/**
 * Finds names in text by plain string matching. Whole words only, so "Al"
 * does not match "also", and case-insensitive.
 */
class NameMatcher
{
    /**
     * Determine if any of the names appears in the text.
     *
     * @param  iterable<string>  $names
     */
    public static function mentions(string $text, iterable $names): bool
    {
        foreach ($names as $name) {
            $name = trim($name);

            if ($name !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if two names are the same, ignoring case and surrounding space.
     */
    public static function same(string $one, string $other): bool
    {
        return mb_strtolower(trim($one)) === mb_strtolower(trim($other));
    }
}
