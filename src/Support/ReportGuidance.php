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
            "<comment>CLOSE THE LOOP on #{$number} — do not wait for a human to relay the resolution.</comment>",
            '  1. PASSIVE (always on): `commandments reports --check` runs at every session start and auto-lifts the absolution when the issue closes — so even days later it resurfaces on its own.',
            '  2. ACTIVE (this session): start a poll loop — paste this:',
            "       /loop 5m Run: gh issue view {$number} --repo {$repo} --json state -q '.state'. If it prints CLOSED, run `composer update jessegall/code-commandments` then re-judge the reported file and act on the result (below), and STOP the loop. If OPEN, do nothing.",
            '  3. WHEN IT CLOSES, act by how it was resolved:',
            '       • fixed / confirmed false positive → `composer update` clears it (re-scaffold if stubs changed).',
            '       • feature / new API shipped → `composer update`, then USE the new API at the sites that motivated the report.',
            '       • closed "works as intended" → the absolution lifts and the finding re-blocks: FIX THE CODE, do not work around it.',
        ];
    }
}
