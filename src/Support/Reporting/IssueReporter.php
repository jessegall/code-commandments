<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Reporting;

use JesseGall\PhpTypes\T_String;

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
        $location = $file !== null ? $file . ($line !== null ? ':' . $line : T_String::empty()) : '(no file given)';

        $title = "[prophet-report] {$short}: " . $this->summarise($reason);

        $body = "**Reported automatically by a code-commandments agent.**\n\n"
            . "| | |\n|---|---|\n"
            . sprintf(
                '| Prophet | `%s` |%s',
                $prophet,
                T_String::NEWLINE,
            )
            . sprintf(
                '| Location | `%s` |%s',
                $location,
                T_String::PARAGRAPH,
            )
            . sprintf(
                '### What\'s wrong%s%s%s',
                T_String::PARAGRAPH,
                $reason,
                T_String::PARAGRAPH,
            );

        if ($snippet !== null && T_String::isNotBlank($snippet)) {
            $body .= "### Flagged code\n\n```php\n" . trim($snippet) . "\n```\n\n";
        }

        $body .= "### For the fixer\n\n"
            . "Decide whether this is a false positive (tighten/guard the prophet), a wrong rule "
            . "(adjust the rule or its config), or correct-but-unclear (improve the message/scripture). "
            . "Add a fixture from the flagged code above.\n";

        return ['title' => $title, 'body' => $body];
    }

    /**
     * Build an ENHANCEMENT issue — a new-prophet / new-feature proposal with no
     * finding attached (so nothing to absolve). Unlike {@see self::build()} it
     * requires no prophet/location; it optionally carries a proposed prophet name
     * and an APPLY/LEAVE rubric for a new-rule proposal.
     *
     * @return array{title: string, body: string}
     */
    public function buildFeatureRequest(string $reason, ?string $title = null, ?string $proposedProphet = null, ?string $rubric = null): array
    {
        $headline = is_string($title) && T_String::isNotBlank($title) ? trim($title) : $this->summarise($reason);

        $body = "**Filed by a code-commandments agent — feature request (no finding attached).**\n\n";

        if (is_string($proposedProphet) && T_String::isNotBlank($proposedProphet)) {
            $body .= "| | |\n|---|---|\n"
                . sprintf('| Proposed prophet | `%s` |%s', trim($proposedProphet), T_String::PARAGRAPH);
        }

        $body .= sprintf('### What / why%s%s%s', T_String::PARAGRAPH, $reason, T_String::PARAGRAPH);

        if (is_string($rubric) && T_String::isNotBlank($rubric)) {
            $body .= sprintf('### Proposed APPLY / LEAVE rubric%s%s%s', T_String::PARAGRAPH, trim($rubric), T_String::PARAGRAPH);
        }

        $body .= "### For the maintainer\n\n"
            . "This is an enhancement / new-rule proposal, not a false-positive report — there is "
            . "no finding to absolve. Decide whether to implement it (new prophet / command / flag), "
            . "then close with the resolution.\n";

        return ['title' => "[feature-request] {$headline}", 'body' => $body];
    }

    /**
     * @param  array{title: string, body: string}  $issue
     * @return array{ok: bool, message: string, url: ?string, number: ?int}
     */
    public function send(array $issue, string $label = 'commandments-report'): array
    {
        if (! $this->ghAvailable()) {
            return [
                'ok' => false,
                'message' => "The `gh` CLI is not available. File this manually at "
                    . sprintf(
                        'https://github.com/%s/issues/new with title:%s%s',
                        $this->repo,
                        T_String::NEWLINE,
                        $issue['title'],
                    ),
                'url' => null,
                'number' => null,
            ];
        }

        $cmd = 'gh issue create'
            . ' --repo ' . escapeshellarg($this->repo)
            . ' --title ' . escapeshellarg($issue['title'])
            . ' --body ' . escapeshellarg($issue['body'])
            . ' --label ' . escapeshellarg($label)
            . ' 2>&1';

        exec($cmd, $output, $code);
        $out = trim(implode(T_String::NEWLINE, $output));

        if ($code !== 0) {
            // Retry without the label (it may not exist on the repo).
            $cmdNoLabel = 'gh issue create'
                . ' --repo ' . escapeshellarg($this->repo)
                . ' --title ' . escapeshellarg($issue['title'])
                . ' --body ' . escapeshellarg($issue['body'])
                . ' 2>&1';

            exec($cmdNoLabel, $retry, $retryCode);
            $out = trim(implode(T_String::NEWLINE, $retry));

            if ($retryCode !== 0) {
                return ['ok' => false, 'message' => "gh issue create failed: {$out}", 'url' => null, 'number' => null];
            }
        }

        $url = $this->extractIssueUrl($out);

        return [
            'ok' => true,
            'message' => "Reported: {$out}",
            'url' => $url,
            'number' => $url !== null ? $this->numberFromUrl($url) : null,
        ];
    }

    private function extractIssueUrl(string $output): ?string
    {
        if (preg_match('#https://github\.com/[^\s]+/issues/\d+#', $output, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    private function numberFromUrl(string $url): ?int
    {
        if (preg_match('#/issues/(\d+)#', $url, $m) === 1) {
            return (int) $m[1];
        }

        return null;
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
        $first = trim(strtok($reason, T_String::NEWLINE) ?: $reason);

        return mb_strlen($first) > 80 ? mb_substr($first, 0, 77) . '…' : $first;
    }
}
