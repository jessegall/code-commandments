<?php

namespace Illuminate\Console\Concerns;

trait CallsCommands
{
    abstract protected function resolveCommand($command);
    public function call($command, array $arguments = array (
)) {}
    public function callSilent($command, array $arguments = array (
)) {}
    public function callSilently($command, array $arguments = array (
)) {}
    protected function runCommand($command, array $arguments, \Symfony\Component\Console\Output\OutputInterface $output) {}
    protected function createInputFromArguments(array $arguments) {}
    protected function context() {}
}
