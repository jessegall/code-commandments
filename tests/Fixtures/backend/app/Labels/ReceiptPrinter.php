<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prints receipt headings from {@see ReceiptGlyph}'s vocabulary. `heading` writes a glyph raw into a
 * slot this very class elsewhere fills by name — the codebase disagreeing with itself, which is the
 * only evidence the rule acts on.
 */
final class ReceiptPrinter
{
    #[Sinful(UnnamedVocabularyLiteral::class)]
    public function heading(string $title): string
    {
        $this->emit(ReceiptGlyph::RULE);

        return $this->emit('•') . $title;
    }

    /**
     * The RESOLUTION — the glyph written under the name that already holds it.
     */
    #[Fixed(UnnamedVocabularyLiteral::class)]
    public function separator(): string
    {
        $this->emit(ReceiptGlyph::RULE);

        return $this->emit(ReceiptGlyph::BULLET);
    }

    /**
     * Righteous: `note` is never called with a glyph constant, so the project decided nothing about
     * its argument and a bullet in prose is just prose.
     */
    #[Righteous(UnnamedVocabularyLiteral::class)]
    public function footer(): string
    {
        return $this->note('• thank you');
    }

    private function emit(string $glyph): string
    {
        return $glyph;
    }

    private function note(string $text): string
    {
        return $text;
    }
}
