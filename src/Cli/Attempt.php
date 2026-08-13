<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use Closure;

/**
 * One rule's turn in a run, survivable: a rule that breaks costs its own work and nothing else, and is
 * REPORTED rather than swallowed. The skip travels back beside the work ({@see skipped}), across a
 * worker boundary, so the run can say a rule never ran — "it found nothing" is the one wrong conclusion
 * to leave a reader with. A project's own rule is named as theirs; we cannot answer for it.
 */
final class Attempt
{
    /**
     * @param  array<array-key, mixed>  $work  what the rule produced — empty when it could not run
     * @param  string|null  $skipped  the rule that could not run, named; null when it ran
     */
    private function __construct(public readonly array $work, public readonly string|null $skipped) {}

    /**
     * @template T of array<array-key, mixed>
     * @param  Closure(): T  $run
     */
    public static function of(string $rule, bool $custom, Closure $run): self
    {
        try {
            return new self($run(), null);
        } catch (\Throwable $failure) {
            $whose = $custom ? " (this project's own, in .commandments/custom/)" : '';

            fwrite(STDERR, "⚠ {$rule}{$whose} failed and was skipped — everything else still ran: {$failure->getMessage()}\n");

            return new self([], $rule);
        }
    }
}
