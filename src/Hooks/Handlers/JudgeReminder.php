<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;


use JesseGall\CodeCommandments\Hooks\Discipline;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use JesseGall\CodeCommandments\Workspace;

/**
 * A "did you judge?" nudge wired to `Stop` and `PreToolUse` hooks; reminds when judged files
 * are touched but unchecked, deduped per changed-file set.
 */
final class JudgeReminder extends Hook implements Discipline
{
    /**
     * The marker section separator: the commit last reminded at sits above it, the explanation below.
     */
    private const string SEPARATOR = '-----';

    /**
     * What the marker file explains about itself, below the commit (the {@see stored} read stops at the separator).
     */
    private const string EXPLANATION = <<<'TXT'
        Batch marker for the code-commandments judge reminder (`commandments judge-reminder`, wired as
        Stop + PreToolUse hooks). The line above the separator is the commit it last reminded at: a
        batch is the work on top of one commit, so it nudges once per batch to run `judge`, silent
        until the next commit, and clears itself when the tree is clean. Safe to delete — it
        regenerates, at most costing you one extra nudge.
        TXT;

    public function summary(): string
    {
        return "Nudges you to `judge` what you changed — before a risky Bash command, and on stop.";
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop'), new HookBinding('PreToolUse', 'Bash')];
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        if (! $event->isGitCommit()) {
            return $this->pass(); // Some other Bash call — not our moment.
        }

        $reason = $this->reminder($event, 'before you commit');

        return $reason === null ? $this->pass() : $this->inject($event, $reason);
    }

    /**
     * Not while a background task runs, and for a reason of its own: this nudge CONSUMES the batch of
     * touched files it reports on. Firing at a stop the agent is only parked at would mark them reminded,
     * so the real stop afterwards — the one with the same dirty files — would say nothing.
     */
    protected function speaksWhileWorkPends(): bool
    {
        return false;
    }

    protected function onStop(HookEvent $event): int
    {
        $reason = $this->reminder($event, 'before you wrap up');

        return $reason === null ? $this->pass() : $this->block($reason);
    }

    /**
     * The nudge to surface, or null to stay silent. Fires only when judged files changed since HEAD AND
     * this batch — the work on top of that commit — has not been reminded yet; deciding to fire records
     * the commit, so every later call stays quiet until the next one. A clean tree clears the marker. Pure of I/O
     * beyond the git reads and the marker it owns, so the once-per-batch behaviour is directly testable.
     */
    public function reminder(HookEvent $event, string $lead = 'before you wrap up'): ?string
    {
        $root = $this->git()->root($event->root);

        if ($root === null) {
            return null; // Not a git repository — nothing to scope a reminder to.
        }

        $ws = Workspace::at($root, $event->sessionId() ?: null);

        // A leftover worklist from a prior `judge` takes priority over "did you judge?" — you already
        // judged; the job now is to finish it, wave by wave.
        $open = $this->openWorklist($ws, $lead);

        if ($open !== null) {
            return $open;
        }

        // The batch is what changed on top of HEAD — work committed long ago on this branch is not in it.
        $tree = $this->git()->workingTree($root);

        if ($tree->changed === []) {
            $this->forget($ws); // Clean tree — the next batch starts fresh.

            return null;
        }

        if ($this->stored($ws) === $tree->head) {
            return null; // This batch was reminded; a new file does not make it a new batch.
        }

        $this->remember($ws, $tree->head);

        return $this->reason(count($tree->changed), $lead);
    }

    /**
     * The "finish your open worklist" nudge, or null. Fires when a prior `judge` left the session's
     * live checklist with sins still in it — once per distinct state, re-arming as lines are
     * worked off (so it keeps saying "keep going, N left" without spamming an unchanged file). A cleared
     * worklist forgets the marker (the session's `.remind-checklist`, recording the state last nudged).
     */
    private function openWorklist(Workspace $ws, string $lead): ?string
    {
        $checklist = Checklist::inSession($ws);
        $remaining = $checklist->remainingSins();
        $marker = $ws->path('.remind-checklist');

        if ($remaining === 0) {
            @unlink($marker);

            return null;
        }

        $fingerprint = $checklist->fingerprint();

        if ($fingerprint !== null && @file_get_contents($marker) === $fingerprint) {
            return null; // Same unchanged worklist already nudged — no new progress to react to.
        }

        @mkdir(dirname($marker), 0777, true);
        @file_put_contents($marker, (string) $fingerprint);

        $noun = $remaining === 1 ? 'sin' : 'sins';

        return "Code Commandments — {$lead}: you have an OPEN worklist with {$remaining} {$noun} still in "
            . "`" . $ws->checklistRelative() . "`. Finish it before you stop: work straight down — fix each at its "
            . 'SOURCE, delete its line — and do NOT re-run judge, re-scan, or re-verify between fixes. '
            . 'Only when the file is EMPTY, run `judge` again (wave by wave; a clean run deletes it). If '
            . 'you are intentionally pausing here, just say so and carry on.';
    }

    private function reason(int $count, string $lead): string
    {
        $noun = $count === 1 ? 'file' : 'files';

        return "Code Commandments — {$lead}: you've changed {$count} judged {$noun} since the last commit. "
            . 'Consider running `vendor/bin/commandments judge --changes` to confirm they conform, and fix any '
            . 'sin at its SOURCE (don\'t launder a finding with a default/cast/null-check). This is a one-time '
            . 'nudge for this batch — if you\'ve already judged, or these changes aren\'t worth a scan, just say '
            . 'so and carry on.';
    }

    private function remember(Workspace $ws, string $head): void
    {
        $file = self::markerFile($ws);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, $head . "\n" . self::SEPARATOR . "\n" . self::EXPLANATION . "\n");
    }

    private function forget(Workspace $ws): void
    {
        @unlink(self::markerFile($ws));
    }

    /**
     * The commit recorded on the marker — the line above the {@see SEPARATOR} — empty when there is none.
     */
    private function stored(Workspace $ws): string
    {
        $file = self::markerFile($ws);

        return is_file($file) ? trim(explode(self::SEPARATOR, (string) file_get_contents($file))[0]) : '';
    }

    private static function markerFile(Workspace $ws): string
    {
        return $ws->path('.judge-reminded');
    }
}
