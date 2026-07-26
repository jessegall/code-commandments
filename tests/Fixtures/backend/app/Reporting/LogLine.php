<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Sinful;

/** A node in a test log tree — it holds its own children, so a failure is knowledge it could answer. */
#[Sinful(InlineDocblock::class)]
#[Sinful(MemberOutOfOrder::class)]
final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /**
     * @var list<LogLine>
     */
    public array $children = [];
}
