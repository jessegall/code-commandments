<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Lays a label out in columns. A different shape of the same sin: the glyph is the SECOND argument
 * here, so the slot that was decided is `pad`'s separator rather than a first parameter.
 */
final class LabelColumns
{
    #[Sinful(UnnamedVocabularyLiteral::class)]
    public function row(string $label, string $value): string
    {
        $this->pad($label, ReceiptGlyph::COLUMN);

        return $this->pad($value, '|');
    }

    /**
     * Righteous: a global function's parameter is shared by every caller in the codebase, so another
     * file joining on a constant says nothing whatever about this join.
     */
    #[Righteous(UnnamedVocabularyLiteral::class)]
    public function joined(array $cells): string
    {
        return implode('|', $cells);
    }

    private function pad(string $text, string $glyph): string
    {
        return $text . $glyph;
    }
}
