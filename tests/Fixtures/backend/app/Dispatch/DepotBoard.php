<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Writes the depot board's heading. It walks from the run through the depot to the depot's address
 * to reach one line of text, so this board now depends on the shape of three objects it never
 * asked for: move the address and the depot and the line moves with them. Below it, the FIX: the
 * run answers for its own heading, and each type asks only its own neighbour.
 */
final class DepotBoard
{
    #[Sinful(TrainWreck::class)]
    public function heading(DepotRun $run): string
    {
        return $run->depot()->address()->town();
    }

    #[Fixed(TrainWreck::class)]
    public function headingTold(DepotRun $run): string
    {
        return $run->town();
    }
}
