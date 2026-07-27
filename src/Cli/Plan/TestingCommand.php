<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Workspace;
/**
 * `commandments testing <show|set|list>` — the agent's handle on the plan's testing methodology
 * ({@see PlanTesting}): show the method in force for this run, or record the one the user chose at
 * approval. Unlike constraints there is no gate — a testing methodology is a working style, verified
 * continuously by the phase tests themselves, not against a diff at completion.
 */
final class TestingCommand implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['testing', 'test-flow'];
    }

    public function help(): Help
    {
        return Help::of("The plan's testing methodology — the working style the user chose at approval, in force for this run.")
            ->form('testing show', 'the methodology in force (the default)')
            ->form('testing set "<methodology>"', 'record the one the user chose')
            ->note('Unlike constraints there is no gate: a testing methodology is verified continuously by the phase '
                . 'tests themselves, not against a diff at completion.');
    }

    public function run(Input $input): int
    {
        $root = $this->io->projectRoot();
        $plan = Config::load($root)->planExecutionSettings();
        $testing = PlanTesting::inSession(Workspace::at($root), $plan);

        return match ($input->firstArgument('show')) {
            'show', 'list', 'status' => $this->show($testing),
            'set', 'use', 'choose' => $this->set($testing, $input),
            default => $this->usage(),
        };
    }

    private function show(PlanTesting $testing): int
    {
        $method = $testing->effective();

        if ($method === '') {
            fwrite(STDOUT, "No testing methodology set for this plan.\n");

            return 0;
        }

        fwrite(STDOUT, "Testing methodology in force for this plan:\n  {$method}\n");

        return 0;
    }

    private function set(PlanTesting $testing, Input $input): int
    {
        $method = trim(implode(' ', array_slice($input->arguments(), 1)));

        if ($method === '') {
            fwrite(STDERR, "Usage: commandments testing set \"<methodology>\"\n");

            return 2;
        }

        $testing->set($method);
        fwrite(STDOUT, "✓ Testing methodology recorded for this plan: {$method}\n");

        return 0;
    }

    private function usage(): int
    {
        return HelpScreen::usage($this);
    }
}
