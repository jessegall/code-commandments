<?php

namespace Shop\Rentals;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Righteous;

final class RentalNotice
{
    #[Righteous(RepeatedFolderPath::class)]
    public function lines(int $daysLate): array
    {
        $template = file(__DIR__ . '/stubs/notice.stub', FILE_IGNORE_NEW_LINES) ?: [];

        return $daysLate > 7 ? array_merge($template, ['A late fee now applies.']) : $template;
    }
}
