<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\CodeSnippet;
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
    public function __construct(private readonly GitHubIssue $github = new GitHubIssue()) {}

    public function names(): array
    {
        return ['report'];
    }

    public function run(Input $input): int
    {
        $detector = $input->option('detector');
        $reason = $input->option('reason');
        $refs = $this->references($input);

        if ($reason === null) {
            return $this->usage();
        }

        // A bug-report must point at the code — that's the whole value of the channel. `--global` is
        // the explicit escape hatch for a defect not tied to any file.
        if ($detector === null && $refs === [] && ! $input->hasFlag('global')) {
            fwrite(STDERR,
                "A bug-report must reference the code it's about. Pass one --ref per file involved:\n"
                . "  commandments report --reason=\"…\" --ref=path/to/File.vue:42 --ref=other/File.ts:10-25\n"
                . "If the bug spans multiple files, you MUST reference EACH of them (repeat --ref).\n"
                . "Only if it genuinely isn't tied to any file, pass --global.\n");

            return 2;
        }

        [$issueTitle, $body] = $detector !== null
            ? ["[detector-report] {$detector}", "**Detector:** `{$detector}`\n\n"]
            : ['[bug-report] ' . ($input->option('title') ?? $this->summarise($reason)), ''];

        $body .= "**Report:**\n{$reason}\n";
        $body .= $this->renderReferences($refs);
        $body .= "\n_Filed via `commandments report` from a consumer project._\n";

        return $this->github->file($issueTitle, $body);
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

        if (($file = $input->option('file')) !== null) {
            $line = $input->option('line');
            $values[] = $line !== null ? "{$file}:{$line}" : $file;
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
            $where = $ref->path . ($ref->startLine !== null ? ':' . $ref->startLine . ($ref->endLine !== null ? "-{$ref->endLine}" : '') : '');
            $out .= "\n**Where:** `{$where}`\n";

            $code = $snippet->forFile($ref->path, $ref->startLine, $ref->endLine);

            if ($code !== null) {
                $out .= "\n{$code}";
            }
        }

        return $out;
    }

    private function usage(): int
    {
        fwrite(STDERR,
            "Usage: commandments report --reason=\"what's wrong\" --ref=path:line [--ref=other:line …]\n"
            . "                           [--detector=NAME] [--title=\"…\"] [--global]\n");

        return 2;
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
