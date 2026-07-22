<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Until;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments until "<condition>"` — the agent's handle on the stop gate ({@see UntilGate}). The
 * user says "keep going until the suite is green"; the agent records that here and the Stop hook
 * ({@see \JesseGall\CodeCommandments\Hooks\Handlers\UntilReminder}) then blocks every stop, telling
 * the agent to verify the condition before it may finish. Unlike the plan nudge this needs no plan:
 * a condition can be set at any moment, in any session.
 * A bare condition (or `add`) sets one, `list` shows what stands, `met <n>` strikes
 * one off as satisfied, `stuck` lets ONE stop through when the agent genuinely can't meet it, and
 * `clear` drops the gate entirely.
 */
final class UntilCommand implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['until'];
    }

    public function run(Input $input): int
    {
        $gate = UntilGate::inSession(Workspace::at($this->io->projectRoot()));

        return match ($input->firstArgument('list')) {
            'add', 'set' => $this->add($gate, $this->text($input, from: 1)),
            'list', 'show', 'status' => $this->list($gate),
            'met', 'done', 'satisfied' => $this->met($gate, (int) ($input->arguments()[1] ?? '0')),
            'stuck', 'blocked' => $this->stuck($gate),
            'clear', 'cancel', 'drop' => $this->clear($gate),
            default => $this->add($gate, $this->text($input, from: 0)), // `until "<condition>"` — the
            // form the user actually speaks; no subcommand needed to set one.
        };
    }

    private function add(UntilGate $gate, string $condition): int
    {
        return $condition === '' ? $this->usage() : $this->announce($condition, $gate->add($condition));
    }

    /**
     * Report a newly-set condition and the number that signs it off.
     */
    private function announce(string $condition, int $number): int
    {
        fwrite(STDOUT,
            "● Stop gate set (condition {$number}): {$condition}\n"
            . "  You may not stop until this holds. Every time you try to stop you will be sent back in to\n"
            . "  verify it — when it genuinely holds, run `commandments until met {$number}`. If you are truly\n"
            . "  blocked and need the user, run `commandments until stuck` (that keeps the condition in force).\n");

        return 0;
    }

    private function list(UntilGate $gate): int
    {
        $conditions = $gate->all();

        if ($conditions === []) {
            fwrite(STDOUT, "○ No stop conditions in force.\n");

            return 0;
        }

        fwrite(STDOUT, "● You may not stop until these hold:\n");

        foreach ($conditions as $index => $condition) {
            fwrite(STDOUT, '  ' . ($index + 1) . ". {$condition}\n");
        }

        return 0;
    }

    private function met(UntilGate $gate, int $number): int
    {
        $condition = $gate->met($number);

        if ($condition === null) {
            fwrite(STDERR, "✗ No condition {$number} — run `commandments until list` to see what stands.\n");

            return 2;
        }

        $remaining = $gate->all();

        fwrite(STDOUT, "✓ Condition met: {$condition}\n" . ($remaining === []
            ? "  The stop gate is lifted — nothing else is holding you.\n"
            : '  ' . count($remaining) . " condition(s) still standing; run `commandments until list` to see them.\n"));

        return 0;
    }

    private function stuck(UntilGate $gate): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, "No stop conditions in force — nothing to be stuck on.\n");

            return 0;
        }

        $gate->markStuck();

        fwrite(STDOUT,
            "◼ Stop gate paused for ONE stop — you are blocked, so hand back to the user and tell them exactly\n"
            . "  which condition you cannot meet and why. The condition stays in force: the gate holds again as\n"
            . "  soon as you continue. (Use `until met <n>` only when a condition actually holds.)\n");

        return 0;
    }

    private function clear(UntilGate $gate): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, "○ No stop conditions in force.\n");

            return 0;
        }

        $gate->clear();
        fwrite(STDOUT, "✓ Stop gate cleared — every condition dropped.\n");

        return 0;
    }

    /**
     * The condition text from the arguments at $from onward — so both `until "a b c"` and an unquoted
     * `until a b c` read the same.
     */
    private function text(Input $input, int $from): string
    {
        return trim(implode(' ', array_slice($input->arguments(), $from)));
    }

    private function usage(): int
    {
        fwrite(STDERR, "Usage: commandments until \"<condition>\" | list | met <n> | stuck | clear\n");

        return 2;
    }
}
