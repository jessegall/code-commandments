<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The header printed above a run sheet. It holds the log, which is its own business — but it reaches
 * from the log through the newest run and through that run's depot to read a code, so three types it
 * never asked to know about are now part of what this header is.
 */
final class RunSheetHeader
{
    public function __construct(private readonly DepotRunLog $runs) {}

    #[Sinful(TrainWreck::class)]
    public function depotCode(): string
    {
        if ($this->runs->sent() === 0) {
            return 'none';
        }

        return $this->runs->newest()->depot()->code;
    }

    public function printed(): string
    {
        return str_pad($this->depotCode(), 8, '.', STR_PAD_RIGHT).' RUN SHEET';
    }
}
