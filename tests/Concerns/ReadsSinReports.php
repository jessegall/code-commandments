<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Concerns;

use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Finding;

/**
 * Assertions over what a sin report says, on the console and in the checklist alike.
 */
trait ReadsSinReports
{
    private function assertReportNeverSays(string $words, Finding ...$findings): void
    {
        $report = new SinReport('/app', $findings);

        $this->assertStringNotContainsString($words, $report->console());
        $this->assertStringNotContainsString($words, $report->checklist());
    }
}
