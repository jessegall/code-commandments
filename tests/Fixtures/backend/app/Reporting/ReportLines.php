<?php

namespace Shop\Reporting;

/**
 * The fixed lines a report opens and closes with.
 */
final class ReportLines
{

    public function __construct(private readonly string $title) {}

    public function heading(): string
    {
        return "# {$this->title}";
    }

    public function footer(): string
    {
        return "-- end of {$this->title}";
    }

}
