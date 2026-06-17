<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Output;

use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\PhpTypes\T_String;

/**
 * Renders exactly ONE finding for the serialized `--next` walk.
 *
 * The whole point of `--next` is that the output is always short enough to
 * be read in full — no wall of findings to truncate. Each render carries
 * the finding, its inline applicability rubric (for advisories), the
 * pointer to the full scripture, and the only two ways forward: fix it, or
 * absolve it with a reason. There is deliberately no "skip".
 */
final class NextFindingPresenter
{
    /**
     * @return list<string>
     */
    public static function lines(Finding $finding, int $totalRemaining, string $binary, bool $absolvable, bool $autoFixable = false): array
    {
        $kind = $finding->isSin() ? '✗ SIN' : '⚠ WARNING';

        $lines = [];
        $lines[] = T_String::empty();
        $lines[] = sprintf('NEXT  [%d remaining]  %s%s', $totalRemaining, $kind, $autoFixable ? '  [AUTO-FIXABLE]' : T_String::empty());
        $lines[] = T_String::empty();
        $lines[] = '  ' . $finding->prophetShort;
        $lines[] = '  ' . $finding->location();
        $lines[] = '  ' . $finding->message;

        if ($finding->snippet !== null && T_String::isNotBlank($finding->snippet)) {
            $lines[] = T_String::empty();
            $lines[] = '    ' . trim($finding->snippet);
        }

        if ($finding->advisory !== null) {
            $lines[] = T_String::empty();
            foreach ($finding->advisory->lines() as $rubricLine) {
                $lines[] = '  ' . $rubricLine;
            }
        }

        if ($finding->suggestion !== null && T_String::isNotBlank($finding->suggestion)) {
            $lines[] = T_String::empty();
            $lines[] = '  → ' . $finding->suggestion;
        }

        if ($autoFixable) {
            $lines[] = T_String::empty();
            $lines[] = 'This is AUTO-FIXABLE — DO NOT fix it by hand. Run:';
            $lines[] = sprintf('  %s repent --git', $binary);
            $lines[] = '  then `' . $binary . ' judge --next` for the next finding.';
            $lines[] = '  (repent rewrites it reliably via AST; hand-fixing wastes effort and risks mistakes.)';
            $lines[] = T_String::empty();
            $lines[] = sprintf('%d finding%s remain. Keep going until none do.', $totalRemaining, $totalRemaining === 1 ? T_String::empty() : 's');

            return $lines;
        }

        $lines[] = T_String::empty();
        $lines[] = 'READ THE FULL RULE BEFORE TOUCHING THIS:';
        $lines[] = sprintf('  %s scripture --prophet=%s', $binary, $finding->prophetShort);
        $lines[] = T_String::empty();
        $lines[] = 'Then do exactly ONE of these — there is no skip:';
        $lines[] = '  1. Fix it, then run:  ' . $binary . ' judge --next';

        if ($absolvable) {
            $lines[] = '  2. If the rule does not apply here, absolve it WITH A REASON:';
            $lines[] = sprintf(
                '       %s absolve --fingerprint=%s --reason="why it does not apply"',
                $binary,
                $finding->fingerprint,
            );
        } else {
            $lines[] = '  (This is a sin — it cannot be absolved. It must be fixed.)';
        }

        $lines[] = T_String::empty();
        $lines[] = sprintf('%d finding%s remain. Keep going until none do.', $totalRemaining, $totalRemaining === 1 ? T_String::empty() : 's');

        return $lines;
    }

    /**
     * The line shown when the queue is empty.
     */
    public static function clearLine(): string
    {
        return 'Righteous: no findings remain. Nothing to fix or absolve.';
    }
}
