<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;


use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use JesseGall\CodeCommandments\Workspace;

/**
 * A "did you judge?" nudge wired to `Stop` and `PreToolUse` hooks; reminds when judged files
 * are touched but unchecked, deduped per changed-file set.
 */
final class JudgeReminder extends Hook
{
    /**
     * The base ref a `--branch` scope compares against — the same default as {@see Scope\Scope}.
     */
    private const string BASE = 'main';

    /**
     * The marker section separator: the reminded file set sits above it, the explanation below.
     */
    private const string SEPARATOR = '-----';

    /**
     * What the marker file explains about itself, below the set (the {@see stored} read stops at the separator).
     */
    private const string EXPLANATION = <<<'TXT'
        Batch marker for the code-commandments judge reminder (`commandments judge-reminder`, wired as
        Stop + PreToolUse hooks). The lines above the separator are the changed-file set it last
        reminded at; the hook nudges once per set to run `judge`, staying silent until a new file is
        touched, and clears itself when the tree is clean. Safe to delete — it regenerates, at most
        costing you one extra nudge.
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
     * The nudge to surface, or null to stay silent. Fires only when judged files (`.php`/`.vue`) are
     * touched AND this batch's set hasn't been reminded yet — deciding to fire records the set, so a
     * subsequent call with no new files stays quiet. A clean tree clears the marker. Pure of I/O
     * beyond the git reads and the marker it owns, so the once-per-batch behaviour is directly testable.
     */
    public function reminder(HookEvent $event, string $lead = 'before you wrap up'): ?string
    {
        $root = $this->git()->root($event->root);

        if ($root === null) {
            return null; // Not a git repository — nothing to scope a reminder to.
        }

        $ws = Workspace::at($root, $event->sessionId() ?: null);

        if (PlanMarker::inSession($ws)->isActive()) {
            return null; // A plan is running — the executing-plans discipline judges ONCE at the end
            // (`checks complete` → `judge --branch`) and commits each phase unjudged on purpose, so a
            // per-commit nudge is noise. It resumes once `plan done` clears the marker.
        }

        // A leftover worklist from a prior `judge` takes priority over "did you judge?" — you already
        // judged; the job now is to finish it, wave by wave.
        $open = $this->openWorklist($ws, $lead);

        if ($open !== null) {
            return $open;
        }

        // Prefer --branch when there's committed branch work beyond the working tree (its set is a
        // superset of the working-tree set), so the nudge covers the whole branch; else --changes.
        $working = $this->git()->changedVsHead($root);
        $branch = $this->git()->changedVsBranch($root, self::BASE) ?? $working;
        $useBranch = count($branch) > count($working);
        $files = array_keys($useBranch ? $branch : $working);

        if ($files === []) {
            $this->forget($ws); // Clean tree — the next batch starts fresh.

            return null;
        }

        if ($this->alreadyReminded($ws, $files)) {
            return null; // No new files since the last nudge this batch.
        }

        $this->remember($ws, $files);

        return $this->reason(count($files), $useBranch, $lead);
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
    private function alreadyReminded(Workspace $ws, array $current): bool
    {
        $stored = $this->stored($ws);

        return $stored !== [] && array_diff($current, $stored) === [];
    }

    /**
     * @param  list<string>  $files
     */
    private function remember(Workspace $ws, array $files): void
    {
        sort($files);

        $file = self::markerFile($ws);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, implode("\n", $files) . "\n" . self::SEPARATOR . "\n" . self::EXPLANATION . "\n");
    }

    private function forget(Workspace $ws): void
    {
        @unlink(self::markerFile($ws));
    }

    /**
     * The file set recorded on the marker — the lines above the {@see SEPARATOR}.
     *
     * @return list<string>
     */
    private function stored(Workspace $ws): array
    {
        $file = self::markerFile($ws);

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

    private static function markerFile(Workspace $ws): string
    {
        return $ws->path('.judge-reminded');
    }
}
