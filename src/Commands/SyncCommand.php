<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\VersionResolver;
use JesseGall\PhpTypes\T_String;

/**
 * Add newly available prophets to the published config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncCommand extends Command
{
    protected $signature = 'commandments:sync
        {--after= : Only add prophets introduced after this version (e.g. 1.4.0). Pass `previous` to use the last synced version automatically.}
        {--dry-run : Show what would be added without modifying the file}';

    protected $description = 'Add newly available prophets to your config file';

    /**
     * Keep the generated tracking state out of version control. Runs on every
     * sync — including the automatic post-merge sync after a package update —
     * so a consumer's .gitignore picks up newly-tracked state files. Stays
     * quiet when nothing changed to avoid noise on routine updates.
     */
    private function ensureGitignore(): void
    {
        $ignoreSkills = (bool) config('commandments.skills.auto_refresh', false);
        $status = (new GitignoreInstaller())->ensure(base_path(), $ignoreSkills);

        match ($status) {
            GitignoreInstaller::STATUS_INSTALLED => $this->line('Created .gitignore with code-commandments state entries'),
            GitignoreInstaller::STATUS_APPENDED => $this->line('Added code-commandments state entries to .gitignore'),
            GitignoreInstaller::STATUS_UPDATED => $this->line('Refreshed code-commandments state entries in .gitignore'),
            GitignoreInstaller::STATUS_WRITE_FAILED => $this->warn('Failed to write .gitignore — check permissions.'),
            GitignoreInstaller::STATUS_ALREADY_PRESENT => null,
        };
    }

    /**
     * Refresh the opt-in plan-loop hook scripts on upgrade (when enabled), so a
     * package update ships fixed/added scripts via the post-merge sync hook. The
     * settings.json wiring is install-hooks/init's job; sync only refreshes the
     * scripts the wiring points at.
     */
    /**
     * Refresh the always-on handoff helper on upgrade so consumers pick up
     * fixes to handoff.sh via the post-merge sync hook.
     */
    private function syncHandoffHelper(): void
    {
        // Only when the consumer already uses the package's Claude hooks (a
        // .claude/hooks dir exists) — a routine sync shouldn't create it for a
        // CLI-only consumer, but should keep/seed the helper for hook users.
        if (! is_dir(base_path('.claude/hooks'))) {
            return;
        }

        if (\JesseGall\CodeCommandments\Support\HandoffHelper::install(base_path()) === \JesseGall\CodeCommandments\Support\HandoffHelper::STATUS_INSTALLED) {
            $this->line('Refreshed the handoff helper at .claude/hooks/handoff.sh');
        }
    }

    private function syncPlanLoopScripts(): void
    {
        if (! (bool) config('commandments.hooks.plan_loop', false)) {
            return;
        }

        if (\JesseGall\CodeCommandments\Support\PlanLoopHookSuite::install(base_path()) === \JesseGall\CodeCommandments\Support\PlanLoopHookSuite::STATUS_INSTALLED) {
            $this->line('Refreshed the plan-loop hook scripts in .claude/hooks/');
        }
    }

    private function autoScaffold(): void
    {
        $scaffold = config('commandments.scaffold', []);

        if (($scaffold['auto'] ?? true) === false) {
            return;
        }

        $results = \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator::packaged()->generate(
            $scaffold['namespace'] ?? 'App\\Support',
            $scaffold['path'] ?? app_path('Support'),
            false,
            $scaffold['except'] ?? [],
        );

        $created = \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter::report(
            $results,
            fn (string $line) => $this->line($line),
        );

        if ($created > 0) {
            $this->info("Generated {$created} new support class(es).");
        }
    }

    private function autoSkills(): void
    {
        $skills = config('commandments.skills', []);

        if (($skills['auto'] ?? true) === false) {
            return;
        }

        $results = \JesseGall\CodeCommandments\Support\Skills\SkillInstaller::packaged()->install(
            config('commandments.scaffold.namespace', 'App\\Support'),
            base_path('.claude/skills'),
            (bool) ($skills['auto_refresh'] ?? false),
            $skills['except'] ?? [],
            (bool) ($skills['auto_refresh'] ?? false),
        );

        $installed = \JesseGall\CodeCommandments\Support\Skills\SkillReporter::report(
            $results,
            fn (string $line) => $this->line($line),
        );

        if ($installed > 0) {
            $this->info("Installed {$installed} new skill(s).");
        }
    }

    public function handle(): int
    {
        $configPath = config_path('commandments.php');

        if (! file_exists($configPath)) {
            $this->error('Config file not found. Run "php artisan vendor:publish --tag=commandments-config" first.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            $this->autoScaffold();
            $this->autoSkills();
            $this->ensureGitignore();
            $this->syncHandoffHelper();
            $this->syncPlanLoopScripts();
        }

        $after = $this->option('after');
        $versionResolver = new VersionResolver();
        $basePath = base_path();

        if ($after === 'previous') {
            $after = $versionResolver->previousSyncedVersion($basePath);

            if ($after === null) {
                $this->warn('No previous sync recorded — falling back to a full sync.');
            } else {
                $this->info("Using previous synced version: {$after}");
            }
        }

        if ($after !== null && ! $this->isValidVersion($after)) {
            $this->error("--after must be a valid semver string (got: {$after})");

            return self::FAILURE;
        }

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath, $after);

        if (empty($result['added'])) {
            $message = $after !== null
                ? "No prophets introduced after {$after}. Nothing to sync."
                : 'All prophets are already registered. Nothing to sync.';
            $this->info($message);

            return self::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $versionTag = $entry['introduced_in'] !== null
                ? " (introduced in {$entry['introduced_in']})"
                : T_String::empty();
            $this->line("  + {$shortName} → {$entry['scroll']}{$versionTag}");
        }

        $count = count($result['added']);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info("{$count} new prophet(s) found (dry run, no changes made).");

            return self::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $currentVersion = $versionResolver->currentVersion();

        if ($currentVersion !== null) {
            $versionResolver->recordSyncedVersion($basePath, $currentVersion);
        }

        $this->newLine();
        $this->info("Synced {$count} new prophet(s) into config/commandments.php.");

        if ($currentVersion !== null) {
            $this->line("Recorded sync version {$currentVersion} in .commandments-last-synced");
        }

        return self::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
