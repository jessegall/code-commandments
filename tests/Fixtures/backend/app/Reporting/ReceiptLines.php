<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Two loops over the same basket, one collecting answers and one reducing it. The line between them is what the
 * loop does with each line: the first hands the line whole to a question THIS class was asked and glues the
 * answers together, which is what a renderer does and cannot move onto a basket that would then know receipt
 * layout; the second picks each line's own figures apart and adds them up, which the basket can answer itself.
 */
final class ReceiptLines
{
    /**
     * Collecting answers to its own question. The concatenation assembles them; it is no arithmetic over the
     * line, since every line goes over whole and `render` owns what one line looks like.
     */
    #[Righteous(FeatureEnvy::class)]
    public function printed(Basket $basket): string
    {
        $text = '';

        foreach ($basket->lines() as $line) {
            $text .= $this->render($line);
        }

        return $text;
    }

    /**
     * Reducing the basket from outside: each line's own figures are read and multiplied here, so the basket is
     * being asked to hold a total it never answers. `$basket->totalCents()` is the fix.
     */
    #[Sinful(FeatureEnvy::class)]
    public function total(Basket $basket): int
    {
        $cents = 0;

        foreach ($basket->lines() as $line) {
            $cents = $cents + $line->priceCents() * $line->count();
        }

        return $cents;
    }

    private function render(ReceiptLine $line): string
    {
        return sprintf("%-20s %5d\n", $line->label(), $line->priceCents());
    }
}
