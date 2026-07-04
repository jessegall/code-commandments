<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * One `commandments` verb — the strategy the {@see Kernel} dispatches to. Each command is
 * standalone: it receives the fully-parsed {@see Input} and returns a process exit code. It never
 * touches `$argv` or re-parses flags — the Input already did that, once.
 */
interface Command
{
    /**
     * The verb(s) this command answers to — `['judge']`, `['disable', 'enable']`. The Kernel keys
     * its registry by these, so registering a command IS wiring it; there is no central switch.
     *
     * @return list<string>
     */
    public function names(): array;

    public function run(Input $input): int;
}
