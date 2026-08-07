<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\CodeSnippet;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\RequiresBestDesign;
/**
 * Files GitHub issues. With `--detector`, files a `[detector-report]` (false positive/wrong rule);
 * without it, a `[bug-report]`. A report must carry its CODE ORIGIN: one or more `--ref=path:line`
 * (or `path:start-end`), repeatable — the referenced source is read and injected into the issue so a
 * maintainer sees the actual code, not a bare `file:line` into a private consumer tree. A bug-report
 * REQUIRES at least one ref (a bug that spans files must reference each); `--global` opts a report out
 * when it genuinely isn't tied to any file (a crash with no args, a CLI-wide defect).
 */
final class Report implements Command
{
    /**
     * Deferral tells that turn a "false positive" into an admission the finding is real. A
     * detector-report reason (or its `--best-design`) is a FLAT assertion — "the code is correct,
     * the detector is wrong" — with no qualifier; the moment it hedges ("correct, BUT it needs its
     * own migration"), everything after the hedge is scope the reporter owns. Matched (padded, so a
     * bare word never trips a longer one) against the lower-cased reason with punctuation flattened
     * to spaces. This is the ONE place a prose blocklist is right — there is no AST for English, and
     * the CLI report channel is outside the parsing layer the no-regex rule guards.
     */
    private const array HEDGES = [
        ' but ', ' later ', ' defer', ' for now', ' out of scope', ' needs its own',
        ' its own change', ' own migration', ' own pr', ' separate change', ' separate pr',
        ' not worth', ' bigger refactor', ' acceptable', ' honest enough', ' good enough',
        ' pre-existing', ' baseline', ' migration-scoped',
    ];

    public function __construct(
        private readonly GitHubIssue $github = new GitHubIssue(),
        private readonly HookIO $io = new HookIO,
    ) {}

    public function names(): array
    {
        return ['report'];
    }

    public function help(): Help
    {
        return Help::of('File a GitHub issue about code-commandments itself (via `gh`) — a false positive, a wrong rule, or a bug.')
            ->form('report --reason="…" --ref=PATH:LINE', 'a [bug-report] — a defect in the tool, pointing at the code it happened on')
            ->form('report --detector=NAME --reason="…" --ref=PATH:LINE', 'a [detector-report] — this finding is a false positive')
            ->option('--reason="…"', "what is wrong (required)")
            ->option('--ref=PATH:LINE', 'the code this is about — repeatable, and PATH:START-END works; the source is read and injected into the issue')
            ->option('--detector=NAME', 'the detector that fired — makes this a false-positive report')
            ->option('--best-design="…"', 'the cleanest design you can conceive for the flagged code; REQUIRED by design-smell detectors')
            ->option('--title="…"', 'the issue title (default: the reason\'s first line)')
            ->option('--global', "opt out of --ref when the defect is tied to no file at all (a crash with no args, a CLI-wide bug)")
            ->option('--file=PATH --line=N', 'the single-ref alias for --ref')
            ->note('Only rules the PACKAGE ships can be reported here. A finding printed as `[Name (custom)]` '
                . 'comes from a detector this project wrote into .commandments/custom/ — fix it there; a report '
                . 'against it is refused.')
            ->note('A report is NOT a deferral. A correct finding must be FIXED, however big the fix — a detector-report '
                . 'asserts flatly that the code is right and the rule is wrong, with no hedge. Some detectors (design smells) REQUIRE --best-design: the report is valid only when the flagged code ALREADY IS that best design.');
    }

    public function run(Input $input): int
    {
        $given = $input->option('reason');
        $refs = $this->references($input);

        if ($given->isNone()) {
            return $this->usage('--reason is required — say what is wrong.');
        }

        $reason = $given->unwrap();

        foreach ($input->option('detector') as $detector) {
            return $this->detectorReport($input, $detector, $reason, $refs);
        }

        // A bug-report must point at the code — that's the whole value of the channel. `--global` is
        // the explicit escape hatch for a defect not tied to any file.
        if ($refs === [] && ! $input->hasFlag('global')) {
            fwrite(STDERR,
                "A bug-report must reference the code it's about. Pass one --ref per file involved:\n"
                . "  commandments report --reason=\"…\" --ref=path/to/File.vue:42 --ref=other/File.ts:10-25\n"
                . "If the bug spans multiple files, you MUST reference EACH of them (repeat --ref).\n"
                . "Only if it genuinely isn't tied to any file, pass --global.\n");

            return 2;
        }

        $title = '[bug-report] ' . $input->option('title')->unwrapOrElse(fn () => $this->summarise($reason));
        $body = "**Report:**\n{$reason}\n"
            . $this->renderReferences($refs)
            . "\n_Filed via `commandments report` from a consumer project._\n";

        return $this->github->file($title, $body);
    }

    /**
     * A detector-report claims ONE thing — the flagged code is CORRECT and the detector is wrong —
     * so it is structurally the code as-is versus the cleanest design the reporter can conceive. We
     * make that explicit and hard to dodge: `--best-design` is REQUIRED (name the cleanest design;
     * a valid report is one where it equals the flagged code), the reason and that design may not
     * HEDGE (a hedge = the finding is real, so implement it), and the design is injected into the
     * issue so a maintainer sees at a glance whether a cleaner design — i.e. the owed fix — was
     * conceded.
     *
     * @param  list<CodeReference>  $refs
     */
    private function detectorReport(Input $input, string $detector, string $reason, array $refs): int
    {
        // The package cannot answer for a rule it does not ship. A project's own detector is the
        // project's to fix, and a report against it goes to a maintainer who has never seen it (#413).
        if (($own = Catalog::named(Custom::detectors($this->io->projectRoot()), $detector)) !== null) {
            fwrite(STDERR,
                "✋ {$detector} is THIS project's own rule — it lives in .commandments/custom/, not in the\n"
                . "  package (" . $own::class . "). Nothing upstream can fix it: tighten the detector there\n"
                . "  (a principled reject, never a name list) or drop it from \$config->detector(...).\n");

            return 2;
        }

        $bestDesign = $input->option('best-design')->filter(static fn (string $design): bool => trim($design) !== '');

        // Only the design-smell detectors that opt in (RequiresBestDesign, on the detector OR its
        // sin) demand a `--best-design`; every other detector-report leaves it optional. So a
        // report against a marked detector without one is refused with the litmus.
        if ($bestDesign->isNone() && $this->requiresBestDesign($detector)) {
            fwrite(STDERR,
                "A detector-report against {$detector} REQUIRES --best-design: the cleanest design you\n"
                . "  can conceive for this code. The litmus — a report is only valid when the flagged code\n"
                . "  ALREADY IS that design. If you can name anything cleaner, THAT design is the owed fix:\n"
                . "  implement it and don't file. Pass it once you've confronted that:\n"
                . "    commandments report --detector={$detector} --reason=\"…\" --best-design=\"…\" --ref=PATH:LINE\n");

            return 2;
        }

        // A detector-report is a FLAT assertion — reject a reason (or a volunteered best-design) that
        // hedges, since a hedge concedes the finding is real. Deferral language is never a report.
        if (($hedge = $this->firstHedge($reason, $bestDesign->unwrapOr(''))) !== null) {
            fwrite(STDERR,
                "✋ Your report hedges (\"{$hedge}\"). A detector-report is a FLAT assertion: the flagged\n"
                . "  code is correct, the detector is wrong — full stop. A hedge means the finding is\n"
                . "  REAL and everything after it is scope you own (cost, timing, \"its own migration\",\n"
                . "  a cascading refactor are never grounds to file). Implement the fix, or restate the\n"
                . "  reason with no qualifier if the code genuinely already is the cleanest design.\n");

            return 2;
        }

        // Printed at the moment of temptation, before anything is filed.
        fwrite(STDERR,
            "⚠ A detector-report claims ONE thing: the flagged code is CORRECT under the\n"
            . "  architecture and the detector is wrong. It is NOT a deferral. If the finding\n"
            . "  is right and an honest fix exists — however big (a migration, a cascading\n"
            . "  refactor, many call sites) — you must IMPLEMENT it; a report of a correct\n"
            . "  finding will be closed and the fix will still be owed.\n");

        $body = "**Detector:** `{$detector}`\n\n"
            . "**Report (why the flagged code is CORRECT and the detector is wrong):**\n{$reason}\n\n"
            . $bestDesign->mapOr('', static fn (string $design): string => "**Cleanest design the reporter can conceive:**\n{$design}\n\n"
                . "> ⚖️ Maintainer litmus: a valid detector-report needs the flagged code to ALREADY BE the\n"
                . "> cleanest design. If the design above differs from the flagged code at all, THAT design is\n"
                . "> the owed fix — close this report; the fix is still owed.\n")
            . $this->renderReferences($refs)
            . "\n_Filed via `commandments report` from a consumer project._\n";

        return $this->github->file("[detector-report] {$detector}", $body);
    }

    /**
     * Whether the named detector (or its sin) opts into the `--best-design` requirement via
     * {@see RequiresBestDesign}. An unknown detector name doesn't — the report still files, so a
     * consumer on a newer/renamed catalog is never blocked by a resolution miss.
     */
    private function requiresBestDesign(string $detector): bool
    {
        $resolved = Catalog::detectorNamed($detector);

        return $resolved instanceof RequiresBestDesign || $resolved?->sin() instanceof RequiresBestDesign;
    }

    /**
     * The first deferral hedge found in any of the given texts, or null when all are flat assertions.
     * Punctuation is flattened to spaces and the haystack padded so a hedge at a clause/string edge
     * is caught and a short token (`but`) never fires inside a longer word (`attribute`).
     */
    private function firstHedge(string ...$texts): ?string
    {
        foreach ($texts as $text) {
            $haystack = ' ' . str_replace(
                ['.', ',', ';', ':', '!', '?', '(', ')', '"', "'", "\n", "\t"],
                ' ',
                mb_strtolower($text),
            ) . ' ';

            foreach (self::HEDGES as $hedge) {
                if (str_contains($haystack, $hedge)) {
                    return trim($hedge);
                }
            }
        }

        return null;
    }

    /**
     * The code origins the report points at — every `--ref` plus, for back-compat, a single
     * `--file`(+`--line`). Blank/unparseable refs are dropped.
     *
     * @return list<CodeReference>
     */
    private function references(Input $input): array
    {
        $values = $input->repeated('ref');

        foreach ($input->option('file') as $file) {
            $values[] = $input->option('line')->mapOr($file, static fn (string $line): string => "{$file}:{$line}");
        }

        return array_values(array_filter(array_map(CodeReference::parse(...), $values)));
    }

    /**
     * Every referenced origin, rendered as a `Where:` line plus its injected code excerpt.
     *
     * @param  list<CodeReference>  $refs
     */
    private function renderReferences(array $refs): string
    {
        $out = '';
        $snippet = new CodeSnippet();

        foreach ($refs as $ref) {
            $out .= "\n**Where:** `{$ref->label()}`\n";

            $code = $snippet->forFile($ref->path, $ref->startLine, $ref->endLine);

            if ($code !== null) {
                $out .= "\n{$code}";
            }
        }

        return $out;
    }

    private function usage(string $message = ''): int
    {
        return HelpScreen::usage($this, $message);
    }

    /**
     * A one-line title from the reason's first line, trimmed to a sane length.
     */
    private function summarise(string $reason): string
    {
        $first = trim((string) strtok($reason, "\n"));

        return mb_strlen($first) > 60 ? mb_substr($first, 0, 57) . '…' : $first;
    }
}
