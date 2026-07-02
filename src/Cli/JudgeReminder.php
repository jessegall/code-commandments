<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * `commandments judge-reminder` — the "did you judge?" nudge, wired to two moments so a commit can't
 * slip past it: a `Stop` hook (turn about to end) and a `PreToolUse` hook on `git commit` (a commit
 * about to land, while the tree is still dirty and detectable — the case a Stop alone misses when the
 * commit is straight to the base branch). When judged files (`.php`/`.vue`) are touched but unchecked
 * it reminds — Stop blocks-and-continues once, PreToolUse injects context and lets the commit run;
 * neither forces, and both let Claude judge or acknowledge and move on.
 *
 * "Once per batch" is keyed on the changed-file SET, not HEAD: a set already reminded (the current
 * set is a subset of it) stays silent — including across the commit that would move HEAD — so the two
 * hooks never double up. Touching a NEW file grows the set and earns a fresh nudge; a clean tree
 * clears the marker so the next batch starts over. Wired by {@see Hooks}, alongside the cardinal-rule
 * {@see Remind} heartbeat.
 */
final class JudgeReminder
{
    /** The base ref a `--branch` scope compares against — the same default as {@see Scope\Scope}. */
    private const string BASE = 'main';

    /** The marker section separator: the reminded file set sits above it, the explanation below. */
    private const string SEPARATOR = '-----';

    public function __construct(private readonly GitFiles $git = new GitFiles) {}

    public function run(array $args): int
    {
        $payload = $this->readPayload();
        $root = $this->projectRoot();

        if (($payload['hook_event_name'] ?? null) === 'PreToolUse') {
            if (! $this->isGitCommit($payload)) {
                return 0; // Some other Bash call — not our moment.
            }

            $reason = $this->reminder($root, 'before you commit');

            if ($reason !== null) {
                $this->inject($reason);
            }

            return 0;
        }

        // Stop (or a manual CLI run): block-and-continue for one more turn.
        $reason = $this->reminder($root, 'before you wrap up');

        if ($reason !== null) {
            $this->block($reason);
        }

        return 0;
    }

    /**
     * The nudge to surface, or null to stay silent. Fires only when judged files (`.php`/`.vue`) are
     * touched AND this batch's set hasn't been reminded yet — deciding to fire records the set, so a
     * subsequent call with no new files stays quiet. A clean tree clears the marker. Pure of I/O
     * beyond the git reads and the marker it owns, so the once-per-batch behaviour is directly testable.
     */
    public function reminder(string $projectRoot, string $lead = 'before you wrap up'): ?string
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
        $files = array_keys($useBranch ? $branch : $working);

        if ($files === []) {
            $this->forget($projectRoot); // Clean tree — the next batch starts fresh.

            return null;
        }

        if ($this->alreadyReminded($projectRoot, $files)) {
            return null; // No new files since the last nudge this batch.
        }

        $this->remember($projectRoot, $files);

        return $this->reason(count($files), $useBranch, $lead);
    }

    /**
     * Is this PreToolUse payload a `git commit` about to run? A real commit, not `commit-graph` or a
     * `--dry-run` rehearsal. Recognises the shell verb; it does not parse code, so no engine is owed.
     */
    private function isGitCommit(array $payload): bool
    {
        if (($payload['tool_name'] ?? null) !== 'Bash') {
            return false;
        }

        $command = (string) ($payload['tool_input']['command'] ?? '');

        return str_contains($command, 'git commit')
            && ! str_contains($command, 'commit-graph')
            && ! str_contains($command, '--dry-run');
    }

    /**
     * A `Stop` block-and-continue: Claude sees $reason and gets one more turn to judge or finish.
     */
    private function block(string $reason): void
    {
        $this->emit(['decision' => 'block', 'reason' => $reason]);
    }

    /**
     * A `PreToolUse` non-blocking nudge: the commit still runs; Claude reads $reason as context.
     */
    private function inject(string $reason): void
    {
        $this->emit(['hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'additionalContext' => $reason,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): void
    {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function reason(int $count, bool $useBranch, string $lead): string
    {
        $noun = $count === 1 ? 'file' : 'files';
        $command = $useBranch
            ? 'vendor/bin/commandments judge --branch'
            : 'vendor/bin/commandments judge --changes';

        return "Code Commandments — {$lead}: you've touched {$count} judged {$noun} (.php/.vue) this "
            . "batch. Consider running `{$command}` to confirm they conform, and fix any sin at its "
            . 'SOURCE (don\'t launder a finding with a default/cast/null-check). This is a one-time '
            . 'nudge for this batch — if you\'ve already judged, or these changes aren\'t worth a '
            . 'scan, just say so and carry on.';
    }

    /**
     * Has this batch's set already been reminded — i.e. is the current set a subset of the stored one?
     * A subset means no new files were touched since, so there's nothing fresh to nudge about.
     *
     * @param  list<string>  $current
     */
    private function alreadyReminded(string $projectRoot, array $current): bool
    {
        $stored = $this->stored($projectRoot);

        return $stored !== [] && array_diff($current, $stored) === [];
    }

    /**
     * @param  list<string>  $files
     */
    private function remember(string $projectRoot, array $files): void
    {
        sort($files);

        $file = self::markerFile($projectRoot);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, implode("\n", $files) . "\n" . self::SEPARATOR . "\n" . self::EXPLANATION . "\n");
    }

    private function forget(string $projectRoot): void
    {
        @unlink(self::markerFile($projectRoot));
    }

    /**
     * The file set recorded on the marker — the lines above the {@see SEPARATOR}.
     *
     * @return list<string>
     */
    private function stored(string $projectRoot): array
    {
        $file = self::markerFile($projectRoot);

        if (! is_file($file)) {
            return [];
        }

        $paths = [];

        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if ($line === self::SEPARATOR) {
                break;
            }

            if ($line !== '') {
                $paths[] = $line;
            }
        }

        return $paths;
    }

    /**
     * The hook payload the harness pipes on STDIN, or an empty array for a manual CLI run (a TTY, or
     * no data). Never blocks on a terminal.
     *
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        if (stream_isatty(STDIN)) {
            return [];
        }

        $data = json_decode((string) stream_get_contents(STDIN), true);

        return is_array($data) ? $data : [];
    }

    private function projectRoot(): string
    {
        return getenv('CLAUDE_PROJECT_DIR') ?: getcwd();
    }

    private static function markerFile(string $projectRoot): string
    {
        return $projectRoot . '/.commandments/.judge-reminded';
    }

    /** What the marker file explains about itself, below the set (the {@see stored} read stops at the separator). */
    private const string EXPLANATION = <<<'TXT'
        Batch marker for the code-commandments judge reminder (`commandments judge-reminder`, wired as
        Stop + PreToolUse hooks). The lines above the separator are the changed-file set it last
        reminded at; the hook nudges once per set to run `judge`, staying silent until a new file is
        touched, and clears itself when the tree is clean. Safe to delete — it regenerates, at most
        costing you one extra nudge.
        TXT;
}
