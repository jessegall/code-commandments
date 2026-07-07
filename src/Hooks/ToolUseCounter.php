<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\ConstraintReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\TestingReminder;
/**
 * A persisted tool-use counter — the shared heartbeat machinery under the reminder hooks ({@see Remind},
 * {@see ConstraintReminder}, {@see TestingReminder}), which each surface their note once every N tool
 * uses. The running count lives on the first line of a `.commandments/` file, with a self-describing
 * explanation below that the `(int)` read ignores. {@see bump} increments and returns the new count,
 * {@see reset} zeroes it, {@see clear} deletes the file (it regenerates). Worktree-scoped by the file
 * path it is built for, and the named factories keep every counter's path in ONE place — so a fresh
 * session can wipe them all via {@see all} without any hook duplicating the path literal.
 */
final class ToolUseCounter
{
    public function __construct(
        private readonly string $path,
        private readonly string $explanation,
    ) {}

    /**
     * Every reminder counter for a worktree — the set a fresh session resets, and the single place
     * each counter's file is named. A new heartbeat hook adds one line here and joins the reset.
     *
     * @return list<self>
     */
    public static function all(string $root): array
    {
        return [
            self::forRemind($root),
            self::forConstraintReminder($root),
            self::forTestingReminder($root),
            self::forWorkingStateReminder($root),
        ];
    }

    public static function forRemind(string $root): self
    {
        return new self($root . '/.commandments/.remind-count', self::note(
            'the code-commandments cardinal-rule reminder',
            'surfaces the "trace to the source" rule once every 25 tool uses, then resets',
        ));
    }

    public static function forConstraintReminder(string $root): self
    {
        return new self($root . '/.commandments/.constraint-remind-count', self::note(
            'the code-commandments constraint reminder',
            're-surfaces the active plan\'s constraints once every 25 tool uses, then resets',
        ));
    }

    public static function forTestingReminder(string $root): self
    {
        return new self($root . '/.commandments/.testing-remind-count', self::note(
            'the code-commandments testing-methodology reminder',
            're-surfaces the active plan\'s testing methodology once every 25 tool uses, then resets',
        ));
    }

    public static function forWorkingStateReminder(string $root): self
    {
        return new self($root . '/.commandments/.working-state-remind-count', self::note(
            'the code-commandments working-state reminder',
            'nudges the agent to refresh its living working-state record once every 25 tool uses, then resets',
        ));
    }

    /**
     * Increment the persisted count and return the new value.
     */
    public function bump(): int
    {
        $count = 1 + (is_file($this->path) ? (int) file_get_contents($this->path) : 0);
        $this->write($count);

        return $count;
    }

    public function reset(): void
    {
        $this->write(0);
    }

    public function clear(): void
    {
        @unlink($this->path);
    }

    private function write(int $count): void
    {
        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, $count . "\n" . $this->explanation . "\n");
    }

    private static function note(string $what, string $does): string
    {
        return "-----\nTool-use counter for {$what} (a PostToolUse hook). The number on the first line is the "
            . "running count; the hook {$does}. Safe to delete — it regenerates.";
    }
}
