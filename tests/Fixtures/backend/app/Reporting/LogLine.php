<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Fixed;
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

    /**
     * A bool about the line itself, named as a claim instead of a question.
     */
    #[Sinful(BareStatePredicate::class)]
    public function reports(): bool
    {
        return $this->level === 'error';
    }
}

/**
 * A node in a test log tree — it holds its own children, so a failure is knowledge it could answer.
 */
#[Fixed(InlineDocblock::class)]
final class LogEntry
{
    public string $level = 'info';

    /**
     * @var list<LogEntry>
     */
    public array $children = [];

    public function isError(): bool
    {
        return $this->level === 'error';
    }
}
