<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\ImpossibleGuard;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Dispatch\RunSheetFigures;

/**
 * Reads a run sheet's figures out loud for the depot board. The sheet it is handed is typed, and
 * the guard below still asks whether it is null and answers with words of its own, so a sheet
 * nobody passed and a sheet of nothing read the same on the board.
 */
final class QuoteSpeech
{
    #[Sinful(ImpossibleGuard::class)]
    public function spoken(RunSheetFigures $sheet): string
    {
        if ($sheet === null) {
            return 'no sheet';
        }

        return $sheet->parcels . ' parcels, ' . $sheet->grams . ' grams';
    }
}
