<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * The characters a printed receipt is built from, and what the printer does with them. Named once so
 * a layout reads as intent rather than punctuation — the vocabulary the printers below are meant to
 * spell their rows with.
 */
#[Fixed(UnnamedVocabularyLiteral::class)]
final class ReceiptGlyph
{
    public const string BULLET = '•';

    public const string RULE = '─';

    public const string COLUMN = '|';

    public const string ELLIPSIS = '…';

    /**
     * A horizontal rule $width glyphs wide.
     */
    public static function ruleOf(int $width): string
    {
        return str_repeat(self::RULE, $width);
    }

    /**
     * Is $char one of the glyphs a receipt is allowed to carry?
     */
    public static function isGlyph(string $char): bool
    {
        return in_array($char, [self::BULLET, self::RULE, self::COLUMN, self::ELLIPSIS], true);
    }
}
