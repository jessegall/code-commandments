<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;

/**
 * One `commandments` verb — the strategy the {@see Kernel} dispatches to. Each command is
 * standalone: it receives the fully-parsed {@see Input} and returns a process exit code. It never
 * touches `$argv` or re-parses flags — the Input already did that, once.
 *
 * A command also DOCUMENTS itself ({@see help}): every help screen, usage error and the skills'
 * command reference are projected from that declaration, so a new subcommand or flag is documented
 * where it is parsed and there is no second list to keep in step.
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

    /**
     * What this verb is, every form it answers to, and the flags it reads — the single source of its
     * documentation. {@see Help\HelpScreen} renders it; a command's own usage error prints it too, so
     * the error a user hits and the help they ask for can never disagree.
     */
    public function help(): Help;

    public function run(Input $input): int;
}
