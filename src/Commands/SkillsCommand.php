<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Skills\SkillDigest;

/**
 * Print the compact skill index. Wired into the session-start hook so an agent
 * always knows the coding-rule playbooks exist and when to read them.
 */
class SkillsCommand extends Command
{
    protected $signature = 'commandments:skills';

    protected $description = 'List the available Code Commandments skills (what they teach + where to read)';

    public function handle(): int
    {
        $this->output->writeln(SkillDigest::render());

        return self::SUCCESS;
    }
}
