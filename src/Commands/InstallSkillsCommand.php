<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillReporter;

/**
 * Publish the Code Commandments skills — the on-demand "how to do it right"
 * playbooks — into the consumer's `.claude/skills/commandments/`. Mirrors
 * `commandments:scaffold`'s --auto refresh hook (auto-managed files with a
 * do-not-edit banner) so they stay current on a package upgrade.
 */
class InstallSkillsCommand extends Command
{
    protected $signature = 'commandments:install-skills
        {--force : Overwrite existing skill files}
        {--auto : Refresh only when skills.auto_refresh is enabled (used by the session-start hook); otherwise do nothing}';

    protected $description = 'Install the Code Commandments skills into .claude/skills/commandments/';

    public function handle(): int
    {
        $config = config('commandments.skills', []);

        // Auto-refresh implies force (the files are auto-managed) and stamps each
        // skill file with a loud do-not-edit banner.
        $autoRefresh = (bool) ($config['auto_refresh'] ?? false);

        // The `--auto` hook is a no-op unless auto-refresh is on.
        if ((bool) $this->option('auto') && ! $autoRefresh) {
            return self::SUCCESS;
        }

        // Skill examples use the consumer's scaffold namespace so they match the
        // generated support classes.
        $namespace = config('commandments.scaffold.namespace', 'App\\Support');
        $except = $config['except'] ?? [];
        $targetRoot = base_path('.claude/skills/commandments');

        $force = $autoRefresh || (bool) $this->option('force');

        $results = SkillInstaller::packaged()
            ->install($namespace, $targetRoot, $force, $except, $autoRefresh);

        $installed = SkillReporter::report($results, fn (string $line) => $this->line($line));

        $this->info($installed > 0
            ? "Installed {$installed} skill(s) into .claude/skills/commandments/."
            : 'All skills already present — nothing to install.');

        return self::SUCCESS;
    }
}
