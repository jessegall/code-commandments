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
 * The constraint heartbeat — a `PostToolUse` hook that, WHILE A PLAN IS ACTIVE and constraints are in
 * force, re-surfaces them once every {@see INTERVAL} tool uses, then resets. Same shape as the
 * cardinal-rule {@see Remind}, but plan-scoped: it keeps the run's invariants ("no frontend logic")
 * present through a long grind so the agent doesn't drift between the phase and completion gates.
 * Silent when no plan is active, when there are no constraints, and on the other 24 of every 25 ticks.
 */
final class ConstraintReminder extends Hook
{
    private const int INTERVAL = 25;

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $active = $this->active($event->root);

        if ($active === [] || $this->bump($event->root) < self::INTERVAL) {
            return $this->pass();
        }

        $this->reset($event->root);
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
    private function active(string $root): array
    {
        if (! PlanMarker::inWorktree($root)->isActive()) {
            return [];
        }

        return PlanConstraints::inWorktree($root, Config::load($root)->planExecutionSettings())->active();
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

    private function bump(string $root): int
    {
        $count = 1 + (is_file($this->counterFile($root)) ? (int) file_get_contents($this->counterFile($root)) : 0);
        $this->write($root, $count);

        return $count;
    }

    private function reset(string $root): void
    {
        $this->write($root, 0);
    }

    private function write(string $root, int $count): void
    {
        $file = $this->counterFile($root);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, $count . "\n" . self::EXPLANATION . "\n");
    }

    private function counterFile(string $root): string
    {
        return $root . '/.commandments/.constraint-remind-count';
    }

    /** What the counter file explains about itself, below the count (the `(int)` read ignores it). */
    private const string EXPLANATION = <<<'TXT'
        -----
        Tool-use counter for the code-commandments constraint reminder (`commandments hook … ConstraintReminder`,
        wired as a PostToolUse hook). The number on the first line is the running count; while a plan is active
        and has constraints, the hook re-surfaces them once every 25 tool uses, then resets. Safe to delete.
        TXT;
}
