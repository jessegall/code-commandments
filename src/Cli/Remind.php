<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments remind` — the cardinal-rule reminder, fired as a `PostToolUse` hook. Rather than
 * re-inject on every turn (noisy) or once at session start (fades), it counts tool uses in a
 * marker file and surfaces the rule once every {@see INTERVAL}, then resets — a steady heartbeat
 * that keeps "trace to the source" present through a long session at a fraction of the token cost.
 * Wired by {@see Install}.
 *
 * The text is a tight distillation of `fix-at-the-source`; it stays short because it recurs.
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
        if ($this->bump() < self::INTERVAL) {
            return $this->pass();
        }

        $this->reset();
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

    /**
     * Increment the persisted tool-use count and return the new value.
     */
    /** What the counter file explains about itself, below the count (the `(int)` read ignores it). */
    private const string EXPLANATION = <<<'TXT'
        -----
        Tool-use counter for the code-commandments reminder hook (`commandments remind`, wired as a
        PostToolUse hook). The number on the first line is the running count; the hook surfaces the
        cardinal rule once every 25 tool uses, then resets it to 0. Safe to delete — it regenerates.
        TXT;

    private function bump(): int
    {
        $file = self::counterFile();
        $count = 1 + (is_file($file) ? (int) file_get_contents($file) : 0);

        $this->write($count);

        return $count;
    }

    private function reset(): void
    {
        $this->write(0);
    }

    private function write(int $count): void
    {
        $file = self::counterFile();

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, $count . "\n" . self::EXPLANATION . "\n");
    }

    private static function counterFile(): string
    {
        $root = getenv('CLAUDE_PROJECT_DIR') ?: getcwd();

        return $root . '/.commandments/.remind-count';
    }
}
