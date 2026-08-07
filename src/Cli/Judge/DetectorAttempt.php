<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use Closure;

/**
 * One detector's run, survivable: a rule that breaks costs its own findings and nothing else, and is
 * REPORTED rather than swallowed — a silent skip reads as "that rule found nothing", the one wrong
 * conclusion to leave a reader with. A project's own rule is named as theirs.
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
