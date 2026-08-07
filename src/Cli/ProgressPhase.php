<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Support\Invokable;

/**
 * One phase of a {@see ProgressBar}, as a `($done, $total)` reporter. Made by
 * {@see ProgressBar::phase}, so a job reports progress against an {@see Invokable} it can also be
 * handed a {@see \JesseGall\CodeCommandments\Support\NoOp} for.
 */
final class ProgressPhase implements Invokable
{
    public function __construct(
        private readonly ProgressBar $bar,
        private readonly string $phase,
    ) {}

    public function __invoke(mixed ...$arguments): mixed
    {
        [$done, $total] = $arguments;

        $this->bar->track((int) $done, (int) $total, $this->phase);

        return null;
    }
}
