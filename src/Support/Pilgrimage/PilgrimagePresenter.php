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
            $lines[] = '   Fix or absolve EVERY location below first.';
        }

        $lines[] = '';
        $lines[] = ' SCRIPTURE';

        foreach (explode("\n", (string) ($step['scripture'] ?? '')) as $scriptureLine) {
            $lines[] = '   ' . $scriptureLine;
        }

        $lines[] = '';
        $lines[] = sprintf(' LOCATIONS (%d) — resolve EVERY one before `next`:', count($locations));

        foreach ($locations as $location) {
            $lines[] = sprintf('   %s:%s', $location['file'], $location['line'] ?? '?');
            $lines[] = '     ' . $location['message'];
        }

        $prophet = (string) ($step['prophet'] ?? '');

        $lines[] = '';
        $lines[] = ' HOW TO RESOLVE EACH LOCATION';
        $lines[] = '   • FIX — edit the file so the rule no longer fires (follow the scripture above).';
        $lines[] = '   • Bulk-fix the auto-fixable ones first:  commandments repent';
        $lines[] = '   • ABSOLVE — only a GENUINE false positive, with a real reason (sins cannot be';
        $lines[] = '     absolved, only advisory findings):';
        $lines[] = sprintf('       commandments absolve --at=<file:line above> --prophet=%s --reason="why this is a real exception"', $prophet);
        $lines[] = '   • REPORT — the rule itself is wrong here:';
        $lines[] = sprintf('       commandments report --prophet=%s --at=<file:line above> --reason="why the rule is wrong"', $prophet);

        $lines[] = '';
        $lines[] = self::THIN;
        $lines[] = ' ⚠ READ THIS ENTIRE OUTPUT. Do NOT head/tail/truncate it — you will miss';
        $lines[] = '   locations and leave the pillar unresolved. Act on every location above.';
        $lines[] = ' When all are fixed or absolved, run `commandments next` — it re-checks this';
        $lines[] = ' prophet and only then advances (forward-only; it never revisits a passed one).';

        return $lines;
    }
}
