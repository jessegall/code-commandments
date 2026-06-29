<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments report --reason="…" [--detector=NAME] [--title="…"] [--file=PATH] [--line=N]`
 *
 * Files an issue so a problem gets fixed upstream instead of being silently ignored.
 * With `--detector` it's a `[detector-report]` (a false positive / wrong rule); the
 * detector is optional, so a GLOBAL bug (a crash, a CLI issue — anything not tied to
 * one detector) files as a `[bug-report]`. Only `--reason` is required.
 */
final class Report
{
    public function run(array $args): int
    {
        $detector = $this->value($args, '--detector=');
        $title = $this->value($args, '--title=');
        $reason = $this->value($args, '--reason=');
        $file = $this->value($args, '--file=');
        $line = $this->value($args, '--line=');

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

    private function value(array $args, string $prefix): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return null;
    }
}
