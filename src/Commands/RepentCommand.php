<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\RepentService;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Auto-fix sins that can be automatically resolved. Thin adapter over
 * {@see RepentService}.
 */
class RepentCommand extends Command
{
    protected $signature = 'commandments:repent
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Use a specific prophet for repentance}
        {--file= : Repent sins in a specific file}
        {--files= : Repent sins in specific files (comma-separated)}
        {--git : Only repent files that are new or changed in git (this is the DEFAULT)}
        {--all : Repent the WHOLE scroll repo-wide (opt-in; the bare command no longer does this)}
        {--input=* : Input for a parameterized fixer, repeatable: --input key=value}
        {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Auto-fix findings that can be automatically resolved — sins and [AUTO-FIXABLE] warnings (no severity bump needed). Defaults to git working-tree scope; pass --all for a repo-wide sweep';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager
    ): int {
        $service = new RepentService(
            $manager,
            $registry,
            $this->output->writeln(...),
        );

        return $service->run([
            'scroll' => $this->option('scroll'),
            'prophet' => $this->option('prophet'),
            'file' => $this->option('file'),
            'files' => $this->option('files') ? array_map('trim', explode(',', $this->option('files'))) : [],
            'git' => (bool) $this->option('git'),
            'all' => (bool) $this->option('all'),
            'dry_run' => (bool) $this->option('dry-run'),
            'input' => (array) $this->option('input'),
        ]) === RepentService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
