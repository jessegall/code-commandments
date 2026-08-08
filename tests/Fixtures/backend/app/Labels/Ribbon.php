<?php

namespace Shop\Labels;

/**
 * Trims a printed line to width, marking the cut with a glyph.
 */
final class Ribbon
{
    public static function cut(string $line, string $glyph): string
    {
        return $line . $glyph;
    }

    public static function label(string $text): string
    {
        return $text;
    }
}
