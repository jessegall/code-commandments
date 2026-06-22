<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\SkillInstallService;

/**
 * Publish the Code Commandments skills — the on-demand "how to do it right"
 * playbooks — into the consumer's `.claude/skills/`. Mirrors
 * `commandments:scaffold`'s --auto refresh hook (auto-managed files with a
 * do-not-edit banner) so they stay current on a package upgrade.
 */
class InstallSkillsCommand extends Command
{
    protected $signature = 'commandments:install-skills
        {--force : Overwrite existing skill files}
        {--auto : Refresh only when skills.auto_refresh is enabled (used by the session-start hook); otherwise do nothing}';

    protected $description = 'Install the Code Commandments skills into .claude/skills/';

    public function handle(): int
    {
        SkillInstallService::install(
            config('commandments', []),
            base_path('.claude/skills'),
            (bool) $this->option('auto'),
            (bool) $this->option('force'),
            fn (string $line) => $this->line($line),
        );

        return self::SUCCESS;
    }
}
