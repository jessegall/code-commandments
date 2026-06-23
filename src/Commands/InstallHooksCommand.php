<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Support\PlanLoopHookSuite;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
use JesseGall\PhpTypes\T_Json;
use JesseGall\PhpTypes\T_String;

/**
 * Install Claude Code hooks for the commandments.
 */
class InstallHooksCommand extends Command
{
    protected $signature = 'commandments:install-hooks
        {--force : Overwrite existing hooks configuration}';

    protected $description = 'Install Claude Code hooks for code commandments (= `profile phased`)';

    public function handle(): int
    {
        $claudeDir = base_path('.claude');
        $settingsFile = $claudeDir.'/settings.json';

        // Create .claude directory if it doesn't exist
        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
            $this->output->writeln('Created .claude directory');
        }

        // Seed the settings.json `instructions` block (the profile switch below
        // merges its hooks INTO this file, preserving the instructions + any
        // consumer keys). Only added when absent.
        $existingSettings = [];
        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            $existingSettings = json_decode($content ?: T_Json::emptyObject(), true) ?? [];
        }

        if (!isset($existingSettings['instructions'])) {
            $existingSettings['instructions'] = $this->getClaudeInstructions();
            $json = json_encode($existingSettings ?: ['instructions' => $this->getClaudeInstructions()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($settingsFile, $json.T_String::NEWLINE);
        }

        // install-hooks IS `profile phased`: the profile owns the Claude hook
        // wiring, the git hooks (pre-commit gate, post-commit reset, commit-msg
        // guard, pre-push reset), and the CLAUDE.md section, and records the
        // selection in .commandments/profile.
        (new ProfileService(base_path(), config('commandments', [])))->switch(
            'phased',
            $this->output->writeln(...),
            $this->warn(...),
        );

        // Install the on-demand "how to do it right" skills into .claude/skills/.
        $this->installSkills();

        // Install the opt-in plan-loop hook scripts when enabled.
        $this->installPlanLoopScripts();

        // Install the always-on handoff helper.
        $this->installHandoffHelper();

        // Keep the generated tracking state out of version control.
        $this->ensureGitignore();

        $this->output->newLine();
        $this->output->writeln('Profile set to "phased". Hooks will:');
        $this->output->writeln('- Show commandments on session start');
        $this->output->writeln('- Judge changed code after Claude completes work');

        if ((bool) config('commandments.hooks.plan_loop', false)) {
            $this->output->writeln('- Drive an approved plan to completion (plan-loop suite) and resolve sins after every commit (phase-committed.sh)');
        } else {
            $this->output->writeln('- Remind Claude to resolve sins after every commit');
        }

        $this->output->writeln('- Block git commits while any sins remain (pre-commit hook)');
        $this->output->writeln('- Clear absolutions after each commit (post-commit hook)');
        $this->output->writeln('Switch modes any time: `php artisan commandments:profile grind` (heads-down) or `disabled`.');

        return self::SUCCESS;
    }

    private function installSkills(): void
    {
        $config = config('commandments.skills', []);
        $autoRefresh = (bool) ($config['auto_refresh'] ?? false);

        $results = \JesseGall\CodeCommandments\Support\Skills\SkillInstaller::packaged()->install(
            config('commandments.scaffold.namespace', 'App\\Support'),
            base_path('.claude/skills'),
            $autoRefresh || (bool) $this->option('force'),
            $config['except'] ?? [],
            $autoRefresh,
        );

        $installed = \JesseGall\CodeCommandments\Support\Skills\SkillReporter::report(
            $results,
            $this->output->writeln(...),
        );

        $this->output->writeln($installed > 0
            ? "Installed {$installed} skill(s) into .claude/skills/"
            : 'Skills already present in .claude/skills/');
    }

    private function installHandoffHelper(): void
    {
        $status = HandoffHelper::install(base_path());

        $this->output->writeln($status === HandoffHelper::STATUS_INSTALLED
            ? 'Installed the handoff helper at .claude/hooks/handoff.sh'
            : 'Failed to write the handoff helper — check permissions.');
    }

    private function installPlanLoopScripts(): void
    {
        if (! (bool) config('commandments.hooks.plan_loop', false)) {
            return;
        }

        $status = PlanLoopHookSuite::install(base_path());

        $this->output->writeln($status === PlanLoopHookSuite::STATUS_INSTALLED
            ? 'Installed the plan-loop hook scripts into .claude/hooks/'
            : 'Failed to write the plan-loop hook scripts — check permissions.');
    }

    private function ensureGitignore(): void
    {
        $ignoreSkills = (bool) config('commandments.skills.auto_refresh', false);
        $status = (new GitignoreInstaller())->ensure(base_path(), $ignoreSkills);

        match ($status) {
            GitignoreInstaller::STATUS_INSTALLED => $this->output->writeln('Created .gitignore with code-commandments state entries'),
            GitignoreInstaller::STATUS_APPENDED => $this->output->writeln('Added code-commandments state entries to .gitignore'),
            GitignoreInstaller::STATUS_UPDATED => $this->output->writeln('Refreshed code-commandments state entries in .gitignore'),
            GitignoreInstaller::STATUS_ALREADY_PRESENT => $this->output->writeln('.gitignore already ignores code-commandments state'),
            GitignoreInstaller::STATUS_WRITE_FAILED => $this->error('Failed to write .gitignore — check permissions.'),
        };
    }

    /**
     * Get Claude instructions for the settings file.
     */
    private function getClaudeInstructions(): string
    {
        return ClaudeMdInstaller::settingsInstructions(base_path());
    }
}
