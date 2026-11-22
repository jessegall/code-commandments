<?php

namespace Illuminate\Console\Concerns;

trait PromptsForMissingInput
{
    /** @return void */
    protected function interact(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    /** @return void */
    protected function promptForMissingArguments(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    /** @return array<string, */
    protected function promptForMissingArgumentsUsing() {}
    /** @return void */
    protected function afterPromptingForMissingArguments(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    /** @return bool */
    protected function didReceiveOptions(\Symfony\Component\Console\Input\InputInterface $input) {}
}
