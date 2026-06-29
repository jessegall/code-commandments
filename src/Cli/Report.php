<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments report --detector=NAME --reason="…" [--file=PATH] [--line=N]`
 *
 * Files a `[detector-report]` issue when a detector fires on code that's genuinely
 * correct (a false positive) or applies a wrong rule — so the detector gets fixed
 * upstream instead of the finding being silently ignored.
 */
final class Report
{
    public function run(array $args): int
    {
        $detector = $this->value($args, '--detector=');
        $reason = $this->value($args, '--reason=');
        $file = $this->value($args, '--file=');
        $line = $this->value($args, '--line=');

        if ($detector === null || $reason === null) {
            fwrite(STDERR, "Usage: commandments report --detector=NAME --reason=\"why this is a false positive / wrong\" [--file=PATH] [--line=N]\n");

            return 2;
        }

        $body = "**Detector:** `{$detector}`\n\n**Report (false positive / wrong rule):**\n{$reason}\n";

        if ($file !== null) {
            $body .= "\n**Where:** `{$file}" . ($line !== null ? ":{$line}" : '') . "`\n";
        }

        $body .= "\n_Filed via `commandments report` from a consumer project._\n";

        return new GitHubIssue()->file("[detector-report] {$detector}", $body);
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
