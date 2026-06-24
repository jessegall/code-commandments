<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

/**
 * Renders one pilgrimage station: the prophet, its full scripture, and EVERY
 * location it fires at — one rule, all its sites, with the context to fix them.
 * The output carries a loud do-not-truncate banner because the agent must act on
 * the whole thing, not a tail of it.
 */
final class PilgrimagePresenter
{
    private const RULE = '═══════════════════════════════════════════════════════════════';

    private const THIN = '───────────────────────────────────────────────────────────────';

    /**
     * @param  array<string, mixed>  $step
     * @return list<string>
     */
    public static function render(array $step, PilgrimageRunner $runner): array
    {
        if (($step['complete'] ?? false) === true) {
            return [
                '',
                '✓ The pilgrimage is complete — every doctrine has been walked.',
                '  Run `commandments pilgrimage` again for a fresh pass to catch anything newly introduced.',
            ];
        }

        $locations = $step['locations'] ?? [];

        $lines = [
            '',
            self::RULE,
            sprintf(' PROPHET   %s', $step['prophet'] ?? '?'),
            sprintf(' PILLAR    %s · doctrine %d/%d', $step['doctrine'] ?? '?', ($step['doctrineIndex'] ?? 0) + 1, $runner->totalDoctrines()),
            self::RULE,
        ];

        if (($step['stillUnresolved'] ?? false) === true) {
            $lines[] = '';
            $lines[] = ' ⚠ STILL UNRESOLVED — `next` will not advance while these remain.';
            $lines[] = '   If you believe a location is fixed, your edit did NOT remove the pattern —';
            $lines[] = '   re-read its message below, fix it properly, or absolve/report it.';
        }

        $lines[] = '';
        $lines[] = ' SCRIPTURE';

        foreach (explode("\n", (string) ($step['scripture'] ?? '')) as $scriptureLine) {
            $lines[] = '   ' . $scriptureLine;
        }

        $autoFixable = count(array_filter($locations, static fn (array $l): bool => ($l['autoFixable'] ?? false) === true));

        $lines[] = '';
        $lines[] = sprintf(' LOCATIONS (%d) — resolve EVERY one before `next`:', count($locations));

        foreach ($locations as $location) {
            $tag = ($location['autoFixable'] ?? false) === true ? ' [AUTO-FIXABLE]' : '';
            $lines[] = sprintf('   %s:%s%s', $location['file'], $location['line'] ?? '?', $tag);
            $lines[] = '     ' . $location['message'];
        }

        $prophet = (string) ($step['prophet'] ?? '');

        $lines[] = '';
        $lines[] = ' HOW TO RESOLVE EACH LOCATION';
        $lines[] = '   • FIX — edit the file so the rule no longer fires (follow the scripture above).';

        if ($autoFixable > 0) {
            $lines[] = sprintf('   • AUTO-FIX — %d of these are [AUTO-FIXABLE]; bulk-fix them first:  commandments repent', $autoFixable);
        }

        $lines[] = '   • ABSOLVE — only a GENUINE false positive, with a real reason (sins cannot be';
        $lines[] = '     absolved, only advisory findings):';
        $lines[] = sprintf('       commandments absolve --at=<file:line above> --prophet=%s --reason="why this is a real exception"', $prophet);
        $lines[] = '';
        $lines[] = ' REPORT A WRONG PROPHET — do this PROACTIVELY, do not just absolve or work around:';
        $lines[] = '   the moment a finding here is a genuine false positive, the rule is wrong/ill-fitting,';
        $lines[] = '   or the prophet has a BUG (a crash, a misleading message, an [AUTO-FIXABLE] that';
        $lines[] = '   no-ops, a bad auto-fix), file it:';
        $lines[] = sprintf('       commandments report --prophet=%s --at=<file:line above> --reason="what is wrong"', $prophet);
        $lines[] = '   It opens a GitHub issue another session fixes — it is how the prophets improve — and';
        $lines[] = '   quiets the finding until the issue is answered. Reporting is part of the job.';

        $lines[] = '';
        $lines[] = self::THIN;
        $lines[] = ' ⚠ READ THIS ENTIRE OUTPUT. Do NOT head/tail/truncate it — you will miss';
        $lines[] = '   locations and leave the pillar unresolved. Act on every location above.';
        $lines[] = ' When all are fixed, absolved, or reported, run `commandments next` — it re-checks';
        $lines[] = ' this prophet and only then advances (forward-only; it never revisits a passed one).';

        return $lines;
    }
}
