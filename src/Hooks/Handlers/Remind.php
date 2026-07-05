<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;


use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\ToolUseCounter;
use JesseGall\CodeCommandments\Cli\Install;
/**
 * PostToolUse hook that counts tool uses and surfaces the cardinal rule once every
 * INTERVAL—a steady heartbeat keeping 'trace to the source' present.
 */
final class Remind extends Hook
{
    private const int INTERVAL = 25;

    private const string REMINDER =
        'Code Commandments — THE MOST IMPORTANT RULE: trace every fix to its SOURCE. '
        . 'A finding is a symptom; fix where the bad value/type/shape is BORN, never where '
        . 'it surfaces. Do NOT silence a detector with a ?? default, cast, null-check, wrapper, '
        . 'constructor override, or try/catch — that launders the problem. If the honest fix '
        . 'touches many call sites, touch them; that breadth is the bug surfacing. '
        . 'And keep to the skills you loaded — they are the standard for every change, not a '
        . 'one-time read; re-open the relevant one before you touch its subject.';

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    /**
     * The heartbeat: every PostToolUse counts one tool use, and the reminder surfaces once the
     * count rolls over the interval — silent on the other 24, so it adds nothing to context. A
     * manual invocation counts the same, so the count is testable outside the harness.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        $counter = ToolUseCounter::forRemind($event->root);

        if ($counter->bump() < self::INTERVAL) {
            return $this->pass();
        }

        $counter->reset();
        $this->io->emit([
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => self::REMINDER,
            ],
        ]);

        return 0;
    }

    protected function onManualRun(HookEvent $event): int
    {
        return $this->onPostToolUse($event);
    }
}
