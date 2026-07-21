<?php

namespace Shop\Reports;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DanglingRouteName;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A mailed digest links back to the daily report. The group prefix here is right and the LEAF is
 * stale — the shape a rename leaves behind, and the one that survives review because the first half
 * of the string still looks familiar.
 */
final class ReportMailer
{
    #[Sinful(DanglingRouteName::class)]
    public function body(string $recipient, int $sales): string
    {
        $greeting = sprintf('Hello %s, you made %d sales.', $recipient, $sales);
        $link = route('reports.dayly');

        return $greeting . ' See the full report: ' . $link;
    }

    #[Righteous(DanglingRouteName::class)]
    public function dailyLink(): string
    {
        return route('reports.daily');
    }
}
