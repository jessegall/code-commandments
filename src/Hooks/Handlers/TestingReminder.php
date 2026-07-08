<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
/**
 * The testing-methodology heartbeat — a `PostToolUse` hook that, while a plan is active and a testing
 * methodology is in force, re-surfaces it once every {@see INTERVAL} tool uses, then resets, so the
 * run's chosen way of writing tests stays present through a long grind (or after a compaction). Silent
 * otherwise. The plan-scoped sibling of {@see ConstraintReminder}.
 */
final class TestingReminder extends Hook
{
    private const int INTERVAL = 25;

    public function summary(): string
    {
        return "Re-surfaces the active plan's testing methodology once every 25 tool uses.";
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $method = $this->active($event->root);
        $counter = Counter::named($event->root, 'testing-remind', "re-surfaces the active plan's testing methodology once every 25 tool uses", every: self::INTERVAL);

        if ($method === '' || ! $counter->due()) {
            return $this->pass();
        }

        $this->io->emit([
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => $this->reminder($method),
            ],
        ]);

        return 0;
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->onPostToolUse($event);
    }

    /**
     * The testing methodology in force for this run, or '' when no plan is active — the reminder is
     * plan-scoped, so a configured default stays dormant until a plan is running.
     */
    private function active(string $root): string
    {
        if (! PlanMarker::inWorktree($root)->isActive()) {
            return '';
        }

        return PlanTesting::inWorktree($root, Config::load($root)->planExecutionSettings())->effective();
    }

    private function reminder(string $method): string
    {
        return 'Code Commandments — hold to this plan\'s TESTING METHODOLOGY as you work: ' . $method;
    }
}
