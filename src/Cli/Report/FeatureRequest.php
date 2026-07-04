<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * Files a [feature-request] GitHub issue proposing a new detector or improvement.
 */
final class FeatureRequest implements Command
{
    public function names(): array
    {
        return ['feature-request'];
    }

    public function run(Input $input): int
    {
        $title = $input->option('title');
        $reason = $input->option('reason');

        if ($title === null || $reason === null) {
            fwrite(STDERR, "Usage: commandments feature-request --title=\"short title\" --reason=\"what to add and why\"\n");

            return 2;
        }

        $body = "**Proposal:**\n{$reason}\n\n_Filed via `commandments feature-request` from a consumer project._\n";

        return new GitHubIssue()->file("[feature-request] {$title}", $body);
    }
}
