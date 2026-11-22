<?php

namespace Illuminate\Console;

class Command extends \Symfony\Component\Console\Command\Command
{
    use \Illuminate\Console\Concerns\CallsCommands, \Illuminate\Console\Concerns\ConfiguresPrompts, \Illuminate\Console\Concerns\HasParameters, \Illuminate\Console\Concerns\InteractsWithIO, \Illuminate\Console\Concerns\InteractsWithSignals, \Illuminate\Console\Concerns\PromptsForMissingInput, \Illuminate\Support\Traits\Macroable;
    protected $laravel = NULL;
    protected $signature = NULL;
    protected $name = NULL;
    protected $description = '';
    protected $help = '';
    protected $hidden = false;
    protected $isolated = false;
    protected $isolatedExitCode = 0;
    protected $aliases = NULL;
    public function __construct() {}
    /** @return void */
    protected function configureUsingFluentDefinition() {}
    /** @return void */
    protected function configureIsolation() {}
    public function run(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int {}
    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int {}
    /** @return \Illuminate\Console\CommandMutex */
    protected function commandIsolationMutex() {}
    /** @return \Symfony\Component\Console\Command\Command */
    protected function resolveCommand($command) {}
    /** @return never */
    public function fail(\Throwable|string|null $exception = NULL) {}
    public function isHidden(): bool {}
    public function setHidden(bool $hidden = true): static {}
    /** @return \Illuminate\Contracts\Foundation\Application */
    public function getLaravel() {}
    /** @return void */
    public function setLaravel($laravel) {}
}
