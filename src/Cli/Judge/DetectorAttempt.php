<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use Closure;

/**
 * One detector's run, survivable: a rule that breaks costs its own findings and nothing else, and is
 * REPORTED rather than swallowed — a silent skip reads as "that rule found nothing", the one wrong
 * conclusion to leave a reader with. So the skip travels back beside the findings ({@see skipped}),
 * across the worker boundary, and the run says so. A project's own rule is named as theirs.
 */
final class DetectorAttempt
{
    /**
     * @param  list<mixed>  $found  what the rule found — empty when it could not run
     * @param  string|null  $skipped  the rule that could not run, named; null when it ran
     */
    private function __construct(public readonly array $found, public readonly string|null $skipped) {}

    /**
     * @template T
     * @param  Closure(): list<T>  $find
     */
    public static function of(string $detector, bool $custom, Closure $find): self
    {
        try {
            return new self($find(), null);
        } catch (\Throwable $failure) {
            $whose = $custom ? " (this project's own, in .commandments/custom/)" : '';

            fwrite(STDERR, "⚠ {$detector}{$whose} failed and was skipped — everything else still ran: {$failure->getMessage()}\n");

            return new self([], $detector);
        }
    }
}
