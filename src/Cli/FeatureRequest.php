<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments feature-request --title="…" --reason="…"`
 *
 * Files a `[feature-request]` issue proposing a new detector or an improvement —
 * the channel for "this rule is missing" or "this should also catch …".
 */
final class FeatureRequest
{
    public function run(array $args): int
    {
        $title = $this->value($args, '--title=');
        $reason = $this->value($args, '--reason=');

        if ($title === null || $reason === null) {
            fwrite(STDERR, "Usage: commandments feature-request --title=\"short title\" --reason=\"what to add and why\"\n");

            return 2;
        }

        $body = "**Proposal:**\n{$reason}\n\n_Filed via `commandments feature-request` from a consumer project._\n";

        return new GitHubIssue()->file("[feature-request] {$title}", $body);
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
