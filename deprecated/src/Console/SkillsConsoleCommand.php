<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Skills\SkillDigest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Print the compact skill index (standalone). Wired into the session-start hook
 * so an agent always knows the coding-rule playbooks exist and when to read them.
 */
class SkillsConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('skills')
            ->setDescription('List the available Code Commandments skills (what they teach + where to read)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(SkillDigest::render());

        return Command::SUCCESS;
    }
}
