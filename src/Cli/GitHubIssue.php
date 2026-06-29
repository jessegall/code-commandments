<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Files a GitHub issue against the code-commandments repo via the `gh` CLI — the
 * channel a consumer uses to report a detector false-positive or propose a rule.
 * Requires `gh` installed and authenticated; otherwise it explains how to file by
 * hand rather than failing silently.
 */
final class GitHubIssue
{
    private const string REPO = 'jessegall/code-commandments';

    public function file(string $title, string $body): int
    {
        if (! $this->ghAvailable()) {
            fwrite(STDERR,
                "GitHub CLI (`gh`) is required to file the issue automatically.\n" .
                "Install it and run `gh auth login`, or open one directly at:\n" .
                "  https://github.com/" . self::REPO . "/issues/new\n",
            );

            return 2;
        }

        $command = 'gh issue create --repo ' . escapeshellarg(self::REPO)
            . ' --title ' . escapeshellarg($title)
            . ' --body ' . escapeshellarg($body)
            . ' 2>&1';

        $output = (string) shell_exec($command);
        fwrite(STDOUT, $output === '' ? "Filed.\n" : $output);

        return str_contains($output, 'github.com') ? 0 : 1;
    }

    private function ghAvailable(): bool
    {
        return trim((string) @shell_exec('command -v gh 2>/dev/null')) !== '';
    }
}
