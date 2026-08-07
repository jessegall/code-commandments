<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use Closure;

/**
 * One detector's run, survivable. A rule that breaks on some shape of code costs its own findings
 * and nothing else: the others have already done their work, and a report missing one of them beats
 * no report at all.
 *
 * It is REPORTED, never swallowed — a silent skip reads as "that rule found nothing", which is the
 * one wrong conclusion to leave a reader with. A project's own rule is named as theirs, because we
 * cannot answer for a rule we did not ship.
 */
final class DetectorAttempt
{
    /**
     * @template T
     * @param  Closure(): list<T>  $find
     * @return list<T>
     */
    public static function of(string $detector, bool $custom, Closure $find): array
    {
        try {
            return $find();
        } catch (\Throwable $failure) {
            $whose = $custom ? " (this project's own, in .commandments/custom/)" : '';

            fwrite(STDERR, "⚠ {$detector}{$whose} failed and was skipped — everything else still ran: {$failure->getMessage()}\n");

            return [];
        }
    }
}
