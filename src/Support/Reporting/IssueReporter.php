<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Reporting;

/**
 * Files a prophet false-positive / wrong-rule report as a GitHub issue on the
 * package repo, so a later session can pick it up and fix the prophet.
 *
 * The issue body is built deterministically (and is unit-testable); sending
 * shells out to the `gh` CLI so it uses the user's existing GitHub auth.
 */
final class IssueReporter
{
    public function __construct(
        private readonly string $repo,
    ) {}

    /**
     * @return array{title: string, body: string}
     */
    public function build(string $prophet, ?string $file, ?int $line, string $reason, ?string $snippet): array
    {
        $short = $this->shortProphet($prophet);
        $location = $file !== null ? $file . ($line !== null ? ':' . $line : '') : '(no file given)';

        $title = "[prophet-report] {$short}: " . $this->summarise($reason);

        $body = "**Reported automatically by a code-commandments agent.**\n\n"
            . "| | |\n|---|---|\n"
            . "| Prophet | `{$prophet}` |\n"
            . "| Location | `{$location}` |\n\n"
            . "### What's wrong\n\n{$reason}\n\n";

        if ($snippet !== null && trim($snippet) !== '') {
            $body .= "### Flagged code\n\n```php\n" . trim($snippet) . "\n```\n\n";
        }

        $body .= "### For the fixer\n\n"
            . "Decide whether this is a false positive (tighten/guard the prophet), a wrong rule "
            . "(adjust the rule or its config), or correct-but-unclear (improve the message/scripture). "
            . "Add a fixture from the flagged code above.\n";

        return ['title' => $title, 'body' => $body];
    }

    /**
     * @param  array{title: string, body: string}  $issue
     * @return array{ok: bool, message: string}
     */
    public function send(array $issue, string $label = 'commandments-report'): array
    {
        if (! $this->ghAvailable()) {
            return [
                'ok' => false,
                'message' => "The `gh` CLI is not available. File this manually at "
                    . "https://github.com/{$this->repo}/issues/new with title:\n{$issue['title']}",
            ];
        }

        $cmd = 'gh issue create'
            . ' --repo ' . escapeshellarg($this->repo)
            . ' --title ' . escapeshellarg($issue['title'])
            . ' --body ' . escapeshellarg($issue['body'])
            . ' --label ' . escapeshellarg($label)
            . ' 2>&1';

        exec($cmd, $output, $code);
        $out = trim(implode("\n", $output));

        if ($code !== 0) {
            // Retry without the label (it may not exist on the repo).
            $cmdNoLabel = 'gh issue create'
                . ' --repo ' . escapeshellarg($this->repo)
                . ' --title ' . escapeshellarg($issue['title'])
                . ' --body ' . escapeshellarg($issue['body'])
                . ' 2>&1';

            exec($cmdNoLabel, $retry, $retryCode);
            $out = trim(implode("\n", $retry));

            if ($retryCode !== 0) {
                return ['ok' => false, 'message' => "gh issue create failed: {$out}"];
            }
        }

        return ['ok' => true, 'message' => "Reported: {$out}"];
    }

    private function ghAvailable(): bool
    {
        exec('command -v gh 2>/dev/null', $o, $code);

        return $code === 0;
    }

    private function shortProphet(string $prophet): string
    {
        $parts = explode('\\', $prophet);

        return end($parts) ?: $prophet;
    }

    private function summarise(string $reason): string
    {
        $first = trim(strtok($reason, "\n") ?: $reason);

        return mb_strlen($first) > 80 ? mb_substr($first, 0, 77) . '…' : $first;
    }
}
