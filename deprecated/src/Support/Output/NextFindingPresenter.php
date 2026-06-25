<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Output;

use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\RepentInput;
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
     * @param  list<RepentInput>|null  $repentInputs  declared inputs when the fixer is parameterized
     * @param  ?string  $skillSlug  the subject skill that teaches this finding's rule (deep-dive pointer), or null
     * @return list<string>
     */
    public static function lines(Finding $finding, int $totalRemaining, string $binary, bool $absolvable, bool $autoFixable = false, ?array $repentInputs = null, ?string $skillSlug = null): array
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

        // Root-cause hint: an unresolved invariant cause sits in this region, so
        // the symptom above is a laundering trap. Shout it in the house imperative
        // voice AND suppress the symptom's own suggestion (it is wrong here).
        if ($finding->rootCauseHint !== null) {
            $hint = $finding->rootCauseHint;
            $lines[] = T_String::empty();
            $lines[] = '  ROOT CAUSE — FIX THIS FIRST. Do NOT act on the finding above:';
            $lines[] = '    This absence is an INVARIANT VIOLATION in disguise, not a genuine absence.';
            $lines[] = '    DO NOT wrap it in Option / a Null Object / a default — that makes the bug permanent and invisible.';
            $lines[] = '    Fix the cause: ' . $hint->reason . '.';
            $lines[] = sprintf('    Read it:  %s scripture --prophet=%s', $binary, $hint->causeShort);
        } elseif ($finding->suggestion !== null && T_String::isNotBlank($finding->suggestion)) {
            $lines[] = T_String::empty();
            $lines[] = '  → ' . $finding->suggestion;

            // The resolver ran the root-cause check and found NONE: a confirmed
            // genuine absence, so the suggestion above really is the right fix.
            if ($finding->rootCauseChecked) {
                $lines[] = '  CHECKED: no invariant cause in-region — this is a genuine absence, so the fix above is correct.';
            }
        }

        if ($autoFixable && $repentInputs !== null && $repentInputs !== []) {
            $lines[] = T_String::empty();
            $lines[] = 'This is AUTO-FIXABLE, but it NEEDS INPUT — DO NOT fix it by hand. Run:';

            // The example carries only REQUIRED inputs — optional/either-or
            // inputs would make one combined command line contradictory.
            $required = collect($repentInputs)
                ->filter(static fn (RepentInput $spec): bool => $spec->required)
                ->values()
                ->all();
            $base = sprintf('  %s repent --prophet=%s --file=%s', $binary, $finding->prophetShort, $finding->relativePath);

            if ($required !== []) {
                $flags = [];
                foreach ($required as $spec) {
                    $example = $spec->example !== '' ? $spec->example : '<value>';
                    $flags[] = sprintf('--input %s=%s', $spec->name, $example);
                }
                $lines[] = $base . ' \\';
                $lines[] = '      ' . implode(' ', $flags);
            } else {
                $lines[] = $base . ' --input <name>=<value> …';
            }

            $lines[] = T_String::empty();
            $lines[] = '  Inputs:';
            foreach ($repentInputs as $spec) {
                $example = $spec->example !== '' ? "  e.g. {$spec->name}={$spec->example}" : T_String::empty();
                $lines[] = sprintf('    %s%s — %s%s', $spec->name, $spec->required ? ' (required)' : ' (optional)', $spec->description, $example);
            }

            $lines[] = T_String::empty();
            $lines[] = '  then `' . $binary . ' judge --next` for the next finding.';
            $lines[] = T_String::empty();
            $lines[] = sprintf('%d finding%s remain. Keep going until none do.', $totalRemaining, $totalRemaining === 1 ? T_String::empty() : 's');

            return $lines;
        }

        if ($autoFixable) {
            $lines[] = T_String::empty();
            $lines[] = 'This is AUTO-FIXABLE — DO NOT fix it by hand. Run:';
            $lines[] = sprintf('  %s repent', $binary);
            $lines[] = '  then `' . $binary . ' judge --next` for the next finding.';
            $lines[] = '  (repent rewrites it reliably via AST; hand-fixing wastes effort and risks mistakes.)';
            $lines[] = '  Agents: run `repent` WITHOUT scope flags — the active profile scopes it. (--git/--branch/--file are for interactive use.)';
            $lines[] = T_String::empty();
            $lines[] = sprintf('%d finding%s remain. Keep going until none do.', $totalRemaining, $totalRemaining === 1 ? T_String::empty() : 's');

            return $lines;
        }

        $lines[] = T_String::empty();
        $lines[] = 'READ THE FULL RULE BEFORE TOUCHING THIS:';
        $lines[] = sprintf('  %s scripture --prophet=%s', $binary, $finding->prophetShort);

        if ($skillSlug !== null) {
            $lines[] = sprintf('  Deep dive: read the `commandments-%s` skill (.claude/skills/commandments-%s/)', $skillSlug, $skillSlug);
        }

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

            if ($finding->isWarning()) {
                $lines[] = sprintf('       · reasoned LEAVE for the whole grind? add --until-push — it survives commits until you push, so you don\'t re-absolve it next commit.');
                $lines[] = sprintf('       · many coincidental LEAVEs at once? %s absolve --warnings --scope=git --reason="…" (one scan; add --prophet=%s to scope to this rule).', $binary, $finding->prophetShort);
            }
        } else {
            $lines[] = '  (This is a sin — FIXING is the default. You own it even if you did not cause it — it is on a file you touched.)';
            $lines[] = '  2. OR, if it is a pre-existing / out-of-scope sin you must NOT touch, absolve it WITH A REASON (a deliberate, audited act — not a shortcut):';
            $lines[] = sprintf(
                '       %s absolve --fingerprint=%s --reason="why this sin is consciously accepted here"',
                $binary,
                $finding->fingerprint,
            );
            $lines[] = '  3. OR, if you believe the rule is genuinely WRONG, report it — that files an issue AND quiets this finding until the issue is answered:';
            $lines[] = sprintf(
                '       %s report --prophet=%s %s --reason="why it is wrong"',
                $binary,
                $finding->prophetShort,
                $finding->line !== null
                    ? '--at=' . $finding->relativePath . ':' . $finding->line
                    : '--file=' . $finding->relativePath,
            );
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
