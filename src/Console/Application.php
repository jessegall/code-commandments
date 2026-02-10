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
        $this->add(new RepentConsoleCommand());
        $this->add(new ScriptureConsoleCommand());
        $this->add(new InitConsoleCommand());
    }
}
