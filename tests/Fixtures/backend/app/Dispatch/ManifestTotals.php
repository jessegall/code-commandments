<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\FilledParameter;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Totals a run sheet. The first method writes the figures onto the sheet it was handed and
 * returns nothing, and its caller reads them straight back off; the one below it is a command
 * through and through, since the caller hands the sheet over and never looks at it again. Both
 * write a field of the sheet, which is feature envy as well, and the fixes differ: the first
 * owes its caller a return, the second owes the sheet a `seal()` of its own.
 */
final class ManifestTotals
{
    public function weigh(RunSheetFigures $sheet): int
    {
        $this->weighParcels($sheet);

        return $sheet->grams;
    }

    #[Sinful(FilledParameter::class)]
    #[Sinful(FeatureEnvy::class)]
    private function weighParcels(RunSheetFigures $sheet): void
    {
        $sheet->grams = $sheet->parcels * 250;
    }

    #[Righteous(FilledParameter::class)]
    #[Sinful(FeatureEnvy::class)]
    public function seal(RunSheetFigures $sheet): void
    {
        $sheet->sealed = true;
    }
}
