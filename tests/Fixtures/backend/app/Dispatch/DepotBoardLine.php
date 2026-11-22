<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\ImpossibleGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * One line of the depot board, written from the figures of the run sheet it was built with.
 */
final class DepotBoardLine
{
    public function __construct(private readonly RunSheetFigures $figures) {}

    #[Sinful(ImpossibleGuard::class)]
    public function weight(): string
    {
        return null !== $this->figures ? $this->figures->grams . ' g' : 'no weight yet';
    }
}
