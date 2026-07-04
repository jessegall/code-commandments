<?php

namespace Shop\Http\Requests;

/**
 * A request a page object injects (hidden) and reads from in its computed slots. Kept minimal — a
 * typed accessor, no raw request scraping.
 */
final class ReportRequest
{
    public function timeRange(): string
    {
        return 'today';
    }
}
