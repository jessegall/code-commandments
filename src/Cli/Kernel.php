<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Hints\Hints;

use JesseGall\CodeCommandments\Hooks\HookDispatch;
use JesseGall\CodeCommandments\Hooks\HookRunner;
use JesseGall\CodeCommandments\Hooks\HookCommand;
use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
use JesseGall\CodeCommandments\Cli\Plan\PlanCommand;
use JesseGall\CodeCommandments\Cli\Plan\ConstraintsCommand;
use JesseGall\CodeCommandments\Cli\Plan\TestingCommand;
use JesseGall\CodeCommandments\Cli\Until\UntilCommand;
use JesseGall\CodeCommandments\Cli\Plan\Checks;
use JesseGall\CodeCommandments\Cli\Config\ConfigCommand;
use JesseGall\CodeCommandments\Cli\Config\Configure;
use JesseGall\CodeCommandments\Cli\Report\Report;
use JesseGall\CodeCommandments\Cli\Report\FeatureRequest;
use JesseGall\CodeCommandments\Cli\Judge\Judge;
use JesseGall\CodeCommandments\Cli\Layers\LayersCommand;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
/**
 * The one entry point behind `bin/commandments`. It parses `$argv` into an {@see Input} exactly
 * once, then dispatches to the {@see Command} registered for the verb — the strategy table that
 * replaced the old `match ($command)`. Registering a command IS wiring it; adding one never edits a
 * switch (nor a usage screen: help is PROJECTED from each command's {@see Command::help}, so the one
 * registration line documents it too). `bin/commandments` does nothing but bootstrap (memory +
 * autoload) and call {@see run}.
 */
final class Kernel
{
    /**
     * @var array<string, Command>  verb => handler
     */
    private array $registry = [];

    public function __construct()
    {
        foreach ($this->commands() as $command) {
            foreach ($command->names() as $name) {
                $this->registry[$name] = $command;
            }
        }
    }

    public function run(array $argv): int
    {
        $input = Input::fromArgv($argv);
        $named = $input->command();

        // `commandments help <verb>` reads as a request for THAT verb's page, exactly like
        // `commandments <verb> --help`; asking for help with no verb named gives the overview.
        if ($this->isHelpVerb($named)) {
            return $this->help($input->firstArgument());
        }

        if ($input->wantsHelp()) {
            return $this->help($named === '' ? null : $named);
        }

        $command = $named === '' ? 'judge' : $named;
        $handler = $this->registry[$command] ?? null;

        if ($handler === null) {
            fwrite(STDERR, "Unknown command '{$command}'. Try: commandments --help\n");

            return 2;
        }

        return $handler->run($input);
    }

    /**
     * The help screen for one verb, or the overview when it names none (or names one we don't have).
     */
    private function help(?string $verb): int
    {
        $screen = new HelpScreen($this->commands());
        $handler = $verb === null ? null : ($this->registry[$verb] ?? null);

        echo $handler === null ? $screen->overview() : $screen->page($handler);

        return 0;
    }

    private function isHelpVerb(string $command): bool
    {
        return in_array($command, ['-h', '--help', 'help'], true);
    }

    /**
     * The registered commands — the whole verb surface. This list is the wiring.
     *
     * @return list<Command>
     */
    public function commands(): array
    {
        return [
            new Judge(),
            new Checks(),
            new Hints(),
            new Repent(),
            new Scaffold(),
            new Report(),
            new FeatureRequest(),
            new Freeze(),
            new Sync(),
            new Install(),
            new HookCommand(['remind'], new Remind()),
            new HookCommand(['judge-reminder'], new JudgeReminder()),
            new HookCommand(['plan-reminder'], new PlanReminder()),
            new PlanCommand(),
            new ConstraintsCommand(),
            new TestingCommand(),
            new UntilCommand(),
            new HookDispatch(),
            new HookRunner(),
            new Configure(),
            new ConfigCommand(),
            new LayersCommand(),
            new Exemptions(),
        ];
    }
}
