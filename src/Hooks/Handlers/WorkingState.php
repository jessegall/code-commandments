<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\ToolUseCounter;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanWorkingState;
/**
 * The working-state discipline — the opt-in ({@see \JesseGall\CodeCommandments\PlanExecution::trackWorkingState})
 * mechanism that keeps the agent's living record ({@see PlanWorkingState}) current through a plan and,
 * above all, alive across context compaction. Three moments, all plan-scoped and toggle-gated:
 * a `PostToolUse` heartbeat nudges a refresh once every {@see INTERVAL} tool uses (a backstop to the
 * skill's after-each-phase/after-each-event discipline); a `PreCompact` flush is the final frontier
 * right before compaction; and a `SessionStart` on `compact`/`resume` re-injects the record so the
 * agent resumes with the full picture. Silent otherwise.
 */
final class WorkingState extends Hook
{
    private const int INTERVAL = 25;

    /** SessionStart sources that CONTINUE a live plan — the ones we re-inject the record on. */
    private const array CONTINUING_SOURCES = ['compact', 'resume'];

    public function summary(): string
    {
        return "Keeps the plan's working-state record alive across compaction — a refresh heartbeat, a PreCompact flush, and re-injection on compact/resume.";
    }

    public function bindings(): array
    {
        return [
            new HookBinding('PostToolUse'),
            new HookBinding('PreCompact'),
            new HookBinding('SessionStart'),
        ];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $counter = ToolUseCounter::forWorkingStateReminder($event->root);

        if (! $this->active($event->root) || $counter->bump() < self::INTERVAL) {
            return $this->pass();
        }

        $counter->reset();
        $this->io->emit([
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => $this->refreshNudge(),
            ],
        ]);

        return 0;
    }

    protected function onPreCompact(HookEvent $event): int
    {
        if (! $this->active($event->root)) {
            return $this->pass();
        }

        return $this->inject($event, $this->flushNudge());
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if (! in_array($event->source(), self::CONTINUING_SOURCES, true) || ! $this->active($event->root)) {
            return $this->pass();
        }

        $record = PlanWorkingState::inWorktree($event->root)->read();

        if ($record === '') {
            return $this->pass();
        }

        return $this->inject($event, $this->recall($record));
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * Working-state tracking is on for THIS run — a plan is active AND the project opted in. Off-plan or
     * without the toggle, every moment stays dormant, exactly like the constraint/testing heartbeats.
     */
    private function active(string $root): bool
    {
        return PlanMarker::inWorktree($root)->isActive()
            && Config::load($root)->planExecutionSettings()->tracksWorkingState();
    }

    private function shape(): string
    {
        return 'Capture ONLY what `git log` + the plan can\'t reconstruct — a Done / Doing / Next cursor, plus '
            . 'the decisions you made (and the alternative you rejected, and WHY), any plan changes agreed in '
            . 'conversation, the gotchas you hit, and the exact next step. Not a restatement of the plan.';
    }

    private function refreshNudge(): string
    {
        return 'Code Commandments — refresh your WORKING STATE now (`.commandments/.plan-working-state`). '
            . $this->shape() . ' It is your lifeline if context compacts.';
    }

    private function flushNudge(): string
    {
        return 'Code Commandments — context is about to COMPACT. Immediately flush anything not yet on disk into '
            . '`.commandments/.plan-working-state`. ' . $this->shape() . ' It is re-injected after compaction, '
            . 'so this is how future-you survives the loss.';
    }

    private function recall(string $record): string
    {
        return "Code Commandments — resuming an active plan. This is your WORKING STATE (from "
            . "`.commandments/.plan-working-state`); treat it as ground truth for where you are and what was "
            . "decided, and keep refreshing it as you work:\n\n" . $record;
    }
}
