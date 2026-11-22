<?php

namespace Illuminate\Console\Concerns;

trait PromptsForMissingInput
{
    protected function interact(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    protected function promptForMissingArguments(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    protected function promptForMissingArgumentsUsing() {}
    protected function afterPromptingForMissingArguments(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    protected function didReceiveOptions(\Symfony\Component\Console\Input\InputInterface $input) {}
}
