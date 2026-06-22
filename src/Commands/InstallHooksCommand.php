<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Support\PlanLoopHookSuite;
use JesseGall\PhpTypes\T_Json;
use JesseGall\PhpTypes\T_String;

/**
 * Install Claude Code hooks for the commandments.
 */
class InstallHooksCommand extends Command
{
    protected $signature = 'commandments:install-hooks
        {--force : Overwrite existing hooks configuration}';

    protected $description = 'Install Claude Code hooks for code commandments';

    public function handle(): int
    {
        $claudeDir = base_path('.claude');
        $settingsFile = $claudeDir.'/settings.json';

        // Create .claude directory if it doesn't exist
        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
            $this->output->writeln('Created .claude directory');
        }

        // Check for existing settings
        $existingSettings = [];
        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            $existingSettings = json_decode($content ?: T_Json::emptyObject(), true) ?? [];
        }

        // Reconcile to the CURRENT package wiring: replace every package-owned
        // entry with the latest set (so changed/removed hooks update cleanly) while
        // preserving every hook the consumer added. Shared with `sync` so an update
        // always lands the newest wiring.
        $hooks = ClaudeHooksInstaller::apply(
            $existingSettings['hooks'] ?? [],
            base_path(),
            (bool) config('commandments.hooks.plan_loop', false),
        );

        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        // Add instructions if not present
        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = $this->getClaudeInstructions();
        }

        // Write settings file
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($settingsFile, $json.T_String::NEWLINE);

        $this->output->writeln('Hooks installed to .claude/settings.json');

        // Create/update CLAUDE.md
        $this->createClaudeMd();

        // Install the on-demand "how to do it right" skills into
        // .claude/skills/ alongside the hooks + CLAUDE.md.
        $this->installSkills();

        // Install the opt-in plan-loop hook scripts when enabled (the settings
        // entries above are already gated on the same flag).
        $this->installPlanLoopScripts();

        // Install the always-on handoff helper (a manual `handoff.sh` the model
        // runs to scaffold HANDOFF.md).
        $this->installHandoffHelper();

        // Install the git pre-commit gate (blocks sins) and post-commit reset
        // (clears absolutions so nothing stays silently hidden).
        $this->installCommitHook();

        // Keep the generated tracking state (.commandments/, report ledger,
        // sync baseline) out of version control.
        $this->ensureGitignore();

        $this->output->newLine();
        $this->output->writeln('Hooks will:');
        $this->output->writeln('- Show commandments on session start');
        $this->output->writeln('- Judge changed code after Claude completes work');

        if ((bool) config('commandments.hooks.plan_loop', false)) {
            $this->output->writeln('- Drive an approved plan to completion (plan-loop suite) and resolve sins after every commit (phase-committed.sh)');
        } else {
            $this->output->writeln('- Remind Claude to resolve sins after every commit');
        }

        $this->output->writeln('- Block git commits while any sins remain (pre-commit hook)');
        $this->output->writeln('- Clear absolutions after each commit (post-commit hook)');

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
            fn (string $line) => $this->output->writeln($line),
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

    private function installCommitHook(): void
    {
        (new CommitHookInstaller())->installAll(
            base_path(),
            (bool) $this->option('force'),
            fn (string $line) => $this->output->writeln($line),
            fn (string $line) => $this->warn($line),
        );
    }

    /**
     * Build the Claude Code hooks configuration.
     */

    /**
     * Get Claude instructions for the settings file.
     */
    private function getClaudeInstructions(): string
    {
        return ClaudeMdInstaller::settingsInstructions(base_path());
    }

    /**
     * Create or update the CLAUDE.md file.
     */
    private function createClaudeMd(): void
    {
        // Shared with init + sync via ClaudeMdInstaller: a sentinel-fenced section,
        // spliced (never preg_replace), runner-parameterized so it can't drift.
        match (ClaudeMdInstaller::install(base_path())) {
            ClaudeMdInstaller::STATUS_CREATED => $this->output->writeln('Created CLAUDE.md'),
            ClaudeMdInstaller::STATUS_APPENDED => $this->output->writeln('Added section to CLAUDE.md'),
            ClaudeMdInstaller::STATUS_REPLACED => $this->output->writeln('Updated CLAUDE.md'),
            ClaudeMdInstaller::STATUS_SKIPPED_CONFLICT => $this->warn('CLAUDE.md has merge conflict markers — skipped the Code Commandments section.'),
            ClaudeMdInstaller::STATUS_WRITE_FAILED => $this->error('Failed to write CLAUDE.md — check permissions.'),
            default => null,
        };
    }

}
