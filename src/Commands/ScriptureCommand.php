<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScriptureService;

/**
 * Reveal the commandments. Thin adapter over {@see ScriptureService}.
 */
class ScriptureCommand extends Command
{
    protected $signature = 'commandments:scripture
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Show details for a specific prophet}
        {--detailed : Show full descriptions with examples}';

    protected $description = 'List all commandments and their descriptions';

    public function handle(ProphetRegistry $registry): int
    {
        return ScriptureService::render(
            $registry,
            $this->option('scroll'),
            $this->option('prophet'),
            (bool) $this->option('detailed'),
            ClaudeHooksInstaller::ARTISAN,
            $this->output->writeln(...),
        ) === ScriptureService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
