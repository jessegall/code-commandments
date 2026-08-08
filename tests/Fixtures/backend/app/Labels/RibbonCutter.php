<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Trims printed lines to width. A third shape: the disagreement travels through a STATIC call rather
 * than a method on `$this`, and the slot is still one the codebase spells by name.
 */
final class RibbonCutter
{
    #[Sinful(UnnamedVocabularyLiteral::class)]
    public function truncated(string $line): string
    {
        Ribbon::cut($line, ReceiptGlyph::ELLIPSIS);

        return Ribbon::cut($line, '…');
    }

    /**
     * Righteous: nothing ever passed a glyph constant to `label`, so its argument was never spelled
     * from the vocabulary and an ellipsis in a caption is just a caption.
     */
    #[Righteous(UnnamedVocabularyLiteral::class)]
    public function caption(): string
    {
        return Ribbon::label('more…');
    }
}
