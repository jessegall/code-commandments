<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\ReportsService;

/**
 * Show the status of prophet reports this project filed, and surface the ones
 * resolved upstream. Thin adapter over {@see ReportsService}.
 */
class ReportsCommand extends Command
{
    protected $signature = 'commandments:reports {--check : Quiet hook mode: print only newly-resolved reports}';

    protected $description = 'Show the status of prophet reports this project filed (resolved upstream yet?)';

    public function handle(ConfessionTracker $tracker): int
    {
        ReportsService::check(
            $tracker,
            base_path(),
            (bool) $this->option('check'),
            $this->line(...),
        );

        return self::SUCCESS;
    }
}
