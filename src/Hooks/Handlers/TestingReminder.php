<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
/**
 * The testing-methodology recall — a `SessionStart` hook that re-surfaces the active plan's chosen way
 * of writing tests on the ONE moment it can go missing: a compaction (or a resume). Never on a timer:
 * a reminder that arrives when nothing prompted it is read once and skimmed thereafter, and it takes
 * the messages that DO report a violation down with it. The plan-scoped sibling of
 * {@see ConstraintReminder}.
 */
final class TestingReminder extends Hook
{
    /**
     * SessionStart sources that CONTINUE a live plan — see {@see ConstraintReminder::CONTINUING_SOURCES}.
     */
    private const array CONTINUING_SOURCES = ['compact', 'resume'];

    public function summary(): string
    {
        return "Re-surfaces the active plan's testing methodology after a compaction or resume.";
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

        $method = $this->active($event);

        if ($method === '') {
            return $this->pass();
        }

        return $this->inject($event, $this->reminder($method));
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->pass();
    }

    /**
     * The testing methodology in force for this run, or '' when no plan is active — the reminder is
     * plan-scoped, so a configured default stays dormant until a plan is running.
     */
    private function active(HookEvent $event): string
    {
        if (! PlanMarker::inSession($event->workspace())->isActive()) {
            return '';
        }

        return PlanTesting::inSession($event->workspace(), Config::load($event->root)->planExecutionSettings())->effective();
    }

    private function reminder(string $method): string
    {
        return 'Code Commandments — hold to this plan\'s TESTING METHODOLOGY as you work: ' . $method;
    }
}
