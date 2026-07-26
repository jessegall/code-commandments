<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A node in a test log tree. It holds its own children, so whether it contains a
 * failure is knowledge it could answer itself — see the envious FailureScanner.
 */
#[Sinful(MemberOutOfOrder::class)]
final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /** @var list<LogLine> */
    public array $children = [];
}
