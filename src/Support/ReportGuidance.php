<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The follow-up guidance appended to a successful `report`, so a filed issue is
 * a CLOSED loop (file → watch → implement-or-fix on close) instead of a
 * fire-and-forget that needs a human to relay the resolution.
 */
final class ReportGuidance
{
    /**
     * @return list<string>
     */
    public static function lines(?int $number, string $repo): array
    {
        if ($number === null) {
            return [];
        }

        return [
            '',
            "<comment>Close the loop on #{$number} — don't wait for a human to relay the resolution:</comment>",
            '  • Long waits (issues can take days): it resurfaces at your next session start via `commandments reports --check` — report-linked absolutions auto-lift when the issue closes.',
            "  • Active session: START A POLL LOOP — schedule a recurring wake-up (e.g. every ~5 min) that runs  gh issue view {$number} --repo {$repo} --json state,stateReason -q '.state'  ; on CLOSED, do the follow-up below and then STOP the loop. (The loop dies with the session, so for multi-day waits rely on `reports --check` too — keep both.)",
            '  • On close, branch by HOW it was resolved:',
            '      – feature / scaffold / php-types request → `composer update jessegall/code-commandments` (re-scaffold if stubs changed), then IMPLEMENT the new API at the call sites that motivated the report.',
            "      – a sin you reported as a false positive, closed \"works as intended\" → the absolution LIFTS and the finding re-blocks: FIX THE CODE, do not work around it.",
            '      – closed as fixed / confirmed false-positive → `composer update` clears it.',
        ];
    }
}
