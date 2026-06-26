<?php

namespace Shop\Reporting;

/**
 * A node in a test log tree. It holds its own children, so whether it contains a
 * failure is knowledge it could answer itself — see the envious FailureScanner.
 */
final class LogLine
{
    public string $level = 'info';

    /** @var list<LogLine> */
    public array $children = [];
}
