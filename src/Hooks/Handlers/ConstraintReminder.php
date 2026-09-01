<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
/**
 * The constraint recall — a `SessionStart` hook that re-surfaces the active plan's constraints on the
 * ONE moment they can go missing: a compaction (or a resume), which drops what was loaded while leaving
 * the agent believing it is still there. Silent otherwise, and never on a timer — an unprompted block
 * arriving on a tool use that broke nothing teaches the reader to clear it unread, and the findings that
 * DO name a violation ({@see SkillReminder}) get cleared with it. The plan-scoped sibling of
 * {@see TestingReminder}, and the same moment {@see WorkingState} recalls its record on.
 */
final class ConstraintReminder extends Hook
{
    /**
     * SessionStart sources that CONTINUE a live plan — the ones the constraints are re-surfaced on. A
     * `startup`/`clear` is a new session, which has no plan of ours to hold constraints for.
     */
    private const array CONTINUING_SOURCES = ['compact', 'resume'];

    public function summary(): string
    {
        return "Re-surfaces the active plan's constraints after a compaction or resume.";
    }

    public function bindings(): array
    {
        return [new HookBinding('SessionStart')];
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if (! in_array($event->source(), self::CONTINUING_SOURCES, true)) {
            return $this->pass();
        }

        $active = $this->active($event);

        if ($active === []) {
            return $this->pass();
        }

        return $this->inject($event, $this->reminder($active));
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * The constraints in force for this run, or [] when no plan is active — the reminder is plan-scoped,
     * so global constraints stay dormant until a plan is running.
     *
     * @return list<string>
     */
    private function active(HookEvent $event): array
    {
        if (! PlanMarker::inSession($event->workspace())->isActive()) {
            return [];
        }

        return PlanConstraints::inSession($event->workspace(), Config::load($event->root)->planExecutionSettings())->active();
    }

    /**
     * @param  list<string>  $constraints
     */
    private function reminder(array $constraints): string
    {
        $list = '';

        foreach ($constraints as $index => $rule) {
            $list .= "\n  " . ($index + 1) . ". {$rule}";
        }

        return 'Code Commandments — hold to this plan\'s CONSTRAINTS; do not drift into a violation as you '
            . 'work (the completion gate will make you review your whole branch diff against them):' . $list;
    }
}
