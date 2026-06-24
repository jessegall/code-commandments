<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Code Commandments', '1.0.0');

        $this->registerCommand(new JudgeConsoleCommand());
        $this->registerCommand(new AbsolveConsoleCommand());
        $this->registerCommand(new RepentConsoleCommand());
        $this->registerCommand(new ScaffoldConsoleCommand());
        $this->registerCommand(new InstallSkillsConsoleCommand());
        $this->registerCommand(new SkillsConsoleCommand());
        $this->registerCommand(new ReportConsoleCommand());
        $this->registerCommand(new ReportsConsoleCommand());
        $this->registerCommand(new ScriptureConsoleCommand());
        $this->registerCommand(new InitConsoleCommand());
        $this->registerCommand(new SyncConsoleCommand());
        $this->registerCommand(new UpdateConsoleCommand());
        $this->registerCommand(new MigrateConfigConsoleCommand());
        $this->registerCommand(new PilgrimageConsoleCommand());
        $this->registerCommand(new NextConsoleCommand());
        $this->registerCommand(new TodoConsoleCommand());
        $this->registerCommand(new AutofixConsoleCommand());
        $this->registerCommand(new InstallSyncHookConsoleCommand());
        $this->registerCommand(new ProfileConsoleCommand());
    }

    /**
     * Register a command across Symfony Console majors. `Application::add()` was
     * deprecated in 7.4 and REMOVED in 8.0 (which Laravel 13 pulls in) in favour of
     * `addCommand()`; older majors only have `add()`. Pick whichever exists (#214).
     */
    private function registerCommand(Command $command): void
    {
        if (method_exists($this, 'addCommand')) {
            $this->addCommand($command);
        } else {
            $this->add($command);
        }
    }
}
