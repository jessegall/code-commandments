<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * `commandments judge-reminder` — the "did you judge?" nudge, fired as a `Stop` hook. When a turn
 * ends with judged files (`.php`/`.vue`) touched but not yet checked, it blocks the stop ONCE with a
 * reminder to run `judge` before wrapping up, then goes silent for the rest of that batch. It reminds
 * (it never forces): Claude reads the reason and can judge, or acknowledge and finish.
 *
 * "Once per batch" is keyed on the HEAD commit: after the single reminder at a given HEAD the marker
 * suppresses it, so more edits in the same batch stay quiet; a commit moves HEAD and opens a fresh
 * batch that may remind again. Nothing changed → silent. Wired by {@see Install}, sibling of the
 * cardinal-rule {@see Remind} heartbeat.
 */
final class JudgeReminder
{
    /** The base ref a `--branch` scope compares against — the same default as {@see Scope\Scope}. */
    private const string BASE = 'main';

    public function __construct(private readonly GitFiles $git = new GitFiles) {}

    public function run(array $args): int
    {
        $reason = $this->reminder($this->projectRoot());

        if ($reason !== null) {
            $this->block($reason);
        }

        return 0;
    }

    /**
     * The nudge to block a `Stop` with, or null to stay silent. Fires only when judged files
     * (`.php`/`.vue`) are touched AND this batch hasn't been nudged yet — deciding to fire also
     * records the batch, so the very next call is silent. Pure of I/O beyond the git reads and the
     * marker it owns, so the once-per-batch behaviour is directly testable.
     */
    public function reminder(string $projectRoot): ?string
    {
        $root = $this->git->root($projectRoot);

        if ($root === null) {
            return null; // Not a git repository — nothing to scope a reminder to.
        }

        // Prefer --branch when there's committed branch work beyond the working tree (its set is a
        // superset of the working-tree set), so the nudge covers the whole branch; else --changes.
        $working = $this->git->changedVsHead($root);
        $branch = $this->git->changedVsBranch($root, self::BASE) ?? $working;
        $useBranch = count($branch) > count($working);
        $files = $useBranch ? $branch : $working;

        if ($files === []) {
            return null; // No judged files touched — stay silent.
        }

        $head = $this->git->head($root);

        if ($this->alreadyReminded($projectRoot, $head)) {
            return null; // Already nudged for this batch (same HEAD).
        }

        $this->remember($projectRoot, $head);

        return $this->reason(count($files), $useBranch);
    }

    /**
     * Emit a `Stop` block-and-continue: Claude sees $reason and gets one more turn to judge or to
     * acknowledge and finish. It fires at most once per batch, so this never loops.
     */
    private function block(string $reason): void
    {
        $payload = ['decision' => 'block', 'reason' => $reason];

        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function reason(int $count, bool $useBranch): string
    {
        $noun = $count === 1 ? 'file' : 'files';
        $command = $useBranch
            ? 'vendor/bin/commandments judge --branch'
            : 'vendor/bin/commandments judge --changes';

        return "Code Commandments — before you wrap up: you've touched {$count} judged {$noun} "
            . "(.php/.vue) this batch. Consider running `{$command}` to confirm they conform, and fix "
            . 'any sin at its SOURCE (don\'t launder a finding with a default/cast/null-check). '
            . 'This is a one-time nudge for this batch — if you\'ve already judged, or these changes '
            . 'aren\'t worth a scan, just say so and finish.';
    }

    private function alreadyReminded(string $projectRoot, string $head): bool
    {
        $file = self::markerFile($projectRoot);

        if (! is_file($file)) {
            return false;
        }

        // The key is the first line; the rest is the self-describing explanation below it.
        $firstLine = strtok((string) file_get_contents($file), "\n");

        return trim((string) $firstLine) === $this->key($head);
    }

    private function remember(string $projectRoot, string $head): void
    {
        $file = self::markerFile($projectRoot);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, $this->key($head) . "\n" . self::EXPLANATION . "\n");
    }

    /** The batch key stored on the marker's first line — the HEAD read by {@see alreadyReminded}. */
    private function key(string $head): string
    {
        return $head === '' ? 'no-head' : $head;
    }

    private function projectRoot(): string
    {
        return getenv('CLAUDE_PROJECT_DIR') ?: getcwd();
    }

    private static function markerFile(string $projectRoot): string
    {
        return $projectRoot . '/.commandments/.judge-reminded';
    }

    /** What the marker file explains about itself, below the key (the first-line read ignores it). */
    private const string EXPLANATION = <<<'TXT'
        -----
        Batch marker for the code-commandments judge reminder (`commandments judge-reminder`, wired as
        a Stop hook). The first line is the HEAD commit it last reminded at; the hook nudges once per
        HEAD to run `judge` on your changes, then stays silent until the next commit. Safe to delete —
        it regenerates, at most costing you one extra nudge.
        TXT;
}
