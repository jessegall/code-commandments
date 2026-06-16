<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Code Commandments', '1.0.0');

        $this->add(new JudgeConsoleCommand());
        $this->add(new AbsolveConsoleCommand());
        $this->add(new RepentConsoleCommand());
        $this->add(new ScaffoldConsoleCommand());
        $this->add(new ReportConsoleCommand());
        $this->add(new ReportsConsoleCommand());
        $this->add(new ScriptureConsoleCommand());
        $this->add(new InitConsoleCommand());
        $this->add(new SyncConsoleCommand());
        $this->add(new InstallSyncHookConsoleCommand());
    }
}
