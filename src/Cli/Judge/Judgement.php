<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Finding;

/**
 * What a run judged: the sins it found, and the rules that could not run. The two travel together
 * because "no sins found" from a set with a broken rule in it is a check reporting success without
 * having run.
 */
final class Judgement
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<string>  $skipped  the rules that broke, named
     */
    public function __construct(public readonly array $findings = [], public readonly array $skipped = []) {}

    public function merge(self $other): self
    {
        return new self(
            [...$this->findings, ...$other->findings],
            [...$this->skipped, ...$other->skipped],
        );
    }

    /**
     * The same verdict over a narrowed finding set — what `--exclude` and the scope leave.
     *
     * @param  list<Finding>  $findings
     */
    public function withFindings(array $findings): self
    {
        return new self($findings, $this->skipped);
    }

    /**
     * A run is clean only when nothing was found AND everything ran.
     */
    public function isClean(): bool
    {
        return $this->findings === [] && $this->skipped === [];
    }
}
