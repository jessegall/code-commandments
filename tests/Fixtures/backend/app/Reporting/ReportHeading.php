<?php

namespace Shop\Reporting;

final class ReportHeading
{
    public function title(ReportTitle $title, string $fallback): string
    {
        return $title->custom ?: $fallback;
    }
}
