<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\CodeSnippet;
/**
 * Files GitHub issues. With `--detector`, files a `[detector-report]` (false positive/wrong
 * rule); without it, a `[bug-report]` for global issues. Only `--reason` required.
 */
final class Report implements Command
{
    public function names(): array
    {
        return ['report'];
    }

    public function run(Input $input): int
    {
        $detector = $input->option('detector');
        $title = $input->option('title');
        $reason = $input->option('reason');
        $file = $input->option('file');
        $line = $input->option('line');

        if ($reason === null) {
            fwrite(STDERR, "Usage: commandments report --reason=\"what's wrong\" [--detector=NAME] [--title=\"…\"] [--file=PATH] [--line=N]\n");

            return 2;
        }

        if ($detector !== null) {
            $issueTitle = "[detector-report] {$detector}";
            $body = "**Detector:** `{$detector}`\n\n";
        } else {
            $issueTitle = '[bug-report] ' . ($title ?? $this->summarise($reason));
            $body = '';
        }

        $body .= "**Report:**\n{$reason}\n";

        if ($file !== null) {
            $body .= "\n**Where:** `{$file}" . ($line !== null ? ":{$line}" : '') . "`\n";

            $snippet = new CodeSnippet()->forFile($file, $line !== null ? (int) $line : null);

            if ($snippet !== null) {
                $body .= "\n{$snippet}";
            }
        }

        $body .= "\n_Filed via `commandments report` from a consumer project._\n";

        return new GitHubIssue()->file($issueTitle, $body);
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
