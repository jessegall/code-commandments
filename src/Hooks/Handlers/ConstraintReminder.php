<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
/**
 * The constraint heartbeat — a `PostToolUse` hook that, while a plan is active and constraints are in
 * force, re-surfaces them once every {@see INTERVAL} tool uses, then resets, so the run's invariants
 * stay present through a long grind. Silent otherwise.
 */
final class ConstraintReminder extends Hook
{
    private const int INTERVAL = 25;

    public function summary(): string
    {
        return "Re-surfaces the active plan's constraints once every 25 tool uses.";
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $active = $this->active($event);
        $counter = Counter::named($event->workspace(), 'constraint-remind', "re-surfaces the active plan's constraints once every 25 tool uses", every: self::INTERVAL);

        if ($active === [] || ! $counter->due()) {
            return $this->pass();
        }

        $this->io->emit([
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => $this->reminder($active),
            ],
        ]);

        return 0;
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->onPostToolUse($event);
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
