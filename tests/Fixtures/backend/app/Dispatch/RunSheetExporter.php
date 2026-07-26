<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Sins\Backend\NarratedCommand;
use JesseGall\CodeCommandments\Sins\Backend\RestatedComment;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Labels\PrintLog;

/**
 * Writes the driver run sheet out for the depot.
 */
final class RunSheetExporter
{
    public function __construct(private readonly PrintLog $log) {}

    /**
     * A void command narrating itself — the depot is being TOLD to take the sheet.
     */
    #[Sinful(NarratedCommand::class)]
    public function announces(string $depot): void
    {
        $this->log->record($depot);
    }

    /**
     * Negating the flag hides nothing: the body is still the choice, and `export($rows, false)`
     * still tells a reader nothing about which of the two exports it asked for.
     */
    #[Sinful(FlagArgument::class)]
    #[Sinful(RestatedComment::class)]
    public function export(string $rows, bool $raw): void
    {
        // check whether it is raw
        if (! $raw) {
            $this->log->record(strtoupper(trim($rows)));
        } else {
            $this->log->record($rows);
        }
    }
}
