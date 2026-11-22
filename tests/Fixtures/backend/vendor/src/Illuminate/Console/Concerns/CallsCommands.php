<?php

namespace Illuminate\Console\Concerns;

trait CallsCommands
{
    /** @return \Symfony\Component\Console\Command\Command */
    abstract protected function resolveCommand($command);
    /** @return int */
    public function call($command, array $arguments = array (
)) {}
    /** @return int */
    public function callSilent($command, array $arguments = array (
)) {}
    /** @return int */
    public function callSilently($command, array $arguments = array (
)) {}
    /** @return int */
    protected function runCommand($command, array $arguments, \Symfony\Component\Console\Output\OutputInterface $output) {}
    /** @return \Symfony\Component\Console\Input\ArrayInput */
    protected function createInputFromArguments(array $arguments) {}
    /** @return array{'--ansi'?: */
    protected function context() {}
}
