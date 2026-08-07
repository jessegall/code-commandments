<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
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

    public function help(): Help
    {
        return Help::of('File a [feature-request] GitHub issue (via `gh`) proposing a new or changed rule.')
            ->form('feature-request --title="…" --reason="…"', 'propose it')
            ->option('--title="…"', 'a short title for the proposal (required)')
            ->option('--reason="…"', 'what to add or change, and why (required)');
    }

    public function run(Input $input): int
    {
        $given = $input->option('title')->zip($input->option('reason'));

        if ($given->isNone()) {
            return HelpScreen::usage($this, '--title and --reason are both required.');
        }

        [$title, $reason] = $given->unwrap();
        $body = "**Proposal:**\n{$reason}\n\n_Filed via `commandments feature-request` from a consumer project._\n";

        return new GitHubIssue()->file("[feature-request] {$title}", $body);
    }
}
