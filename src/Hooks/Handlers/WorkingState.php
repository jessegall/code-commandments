<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanWorkingState;
/**
 * The working-state discipline — the opt-in ({@see \JesseGall\CodeCommandments\PlanExecution::trackWorkingState})
 * mechanism that keeps the agent's living record ({@see PlanWorkingState}) current through a plan and,
 * above all, alive across context compaction. Two moments, both plan-scoped and toggle-gated: a
 * `PostToolUse` heartbeat nudges a refresh once every {@see INTERVAL} tool uses, keeping the record
 * current ahead of a compaction that arrives unannounced; a `SessionStart` on `compact`/`resume`
 * re-injects it so the agent resumes with the full picture. Silent otherwise.
 */
final class WorkingState extends Hook
{
    private const int INTERVAL = 25;

    /**
     * SessionStart sources that CONTINUE a live plan — the ones we re-inject the record on.
     */
    private const array CONTINUING_SOURCES = ['compact', 'resume'];

    public function summary(): string
    {
        return "Keeps the plan's working-state record alive across compaction — a refresh heartbeat and re-injection on compact/resume.";
    }

    public function bindings(): array
    {
        return [
            new HookBinding('PostToolUse'),
            new HookBinding('SessionStart'),
        ];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $counter = Counter::named($event->workspace(), 'working-state-remind', 'nudges a refresh of the living working-state record once every 25 tool uses', every: self::INTERVAL);

        if (! $this->active($event) || ! $counter->due()) {
            return $this->pass();
        }

        return $this->quietly($event, $this->refreshNudge($this->record($event)->path()));
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if (! in_array($event->source(), self::CONTINUING_SOURCES, true) || ! $this->active($event)) {
            return $this->pass();
        }

        $record = $this->record($event)->read();

        if ($record === '') {
            return $this->pass();
        }

        return $this->inject($event, $this->recall($record));
    }

    private function record(HookEvent $event): PlanWorkingState
    {
        return PlanWorkingState::inSession($event->workspace());
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * Working-state tracking is on for THIS run — a plan is active AND the project opted in. Off-plan or
     * without the toggle, every moment stays dormant, exactly like the constraint/testing heartbeats.
     */
    private function active(HookEvent $event): bool
    {
        return PlanMarker::inSession($event->workspace())->isActive()
            && Config::load($event->root)->planExecutionSettings()->isWorkingStateTracked();
    }

    private function shape(): string
    {
        return 'Capture ONLY what `git log` + the plan can\'t reconstruct — a Done / Doing / Next cursor, plus '
            . 'the decisions you made (and the alternative you rejected, and WHY), any plan changes agreed in '
            . 'conversation, the gotchas you hit, and the exact next step. Not a restatement of the plan.';
    }

    private function refreshNudge(string $path): string
    {
        return "Code Commandments — refresh your WORKING STATE now (`{$path}`). "
            . $this->shape() . ' It is your lifeline if context compacts.';
    }

    private function recall(string $record): string
    {
        return "Code Commandments — resuming an active plan. This is your WORKING STATE (from the plan's "
            . "working-state record); treat it as ground truth for where you are and what was "
            . "decided, and keep refreshing it as you work:\n\n" . $record;
    }
}
