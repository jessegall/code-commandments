<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use JesseGall\CodeCommandments\Support\ConfigGenerator;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Support\PlanLoopHookSuite;
use JesseGall\CodeCommandments\Support\ProjectDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_Json;
use JesseGall\PhpTypes\T_String;

/**
 * One-command setup for standalone (non-Laravel) projects.
 * Creates config file, Claude Code hooks, and CLAUDE.md.
 */
class InitConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize code commandments for a standalone project')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files')
            ->addOption('auto-detect', null, InputOption::VALUE_NONE, 'Auto-detect projects and generate config');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd();
        $force = (bool) $input->getOption('force');
        $autoDetect = (bool) $input->getOption('auto-detect');

        $this->createConfig($basePath, $autoDetect, $output);
        $this->createClaudeHooks($basePath, $force, $output);
        $this->createClaudeMd($basePath, $output);
        $this->installSkills($basePath, $force, $output);
        $this->installHandoffHelper($basePath, $output);
        $this->installPlanLoopScripts($basePath, $output);
        $this->installCommitHook($basePath, $force, $output);
        $this->ensureGitignore($basePath, $output);

        $output->writeln(T_String::empty());
        $output->writeln('Done! Next steps:');

        if ($autoDetect) {
            $output->writeln('  1. Review the generated commandments.php');
            $output->writeln('  2. Run: vendor/bin/commandments judge');
        } else {
            $output->writeln('  1. Edit commandments.php to configure your scrolls and prophets');
            $output->writeln('  2. Run: vendor/bin/commandments judge');
        }

        return Command::SUCCESS;
    }

    private function installSkills(string $basePath, bool $force, OutputInterface $output): void
    {
        $resolved = ConfigLoader::resolve(null, $basePath);
        $config = $resolved !== null ? ConfigLoader::load($resolved) : [];

        $skills = $config['skills'] ?? [];
        $autoRefresh = (bool) ($skills['auto_refresh'] ?? false);

        $results = \JesseGall\CodeCommandments\Support\Skills\SkillInstaller::packaged()->install(
            $config['scaffold']['namespace'] ?? 'App\\Support',
            $basePath . '/.claude/skills',
            $autoRefresh || $force,
            $skills['except'] ?? [],
            $autoRefresh,
        );

        $installed = \JesseGall\CodeCommandments\Support\Skills\SkillReporter::report(
            $results,
            fn (string $line) => $output->writeln($line),
        );

        $output->writeln($installed > 0
            ? "Installed {$installed} skill(s) into .claude/skills/"
            : 'Skills already present in .claude/skills/');
    }

    private function ensureGitignore(string $basePath, OutputInterface $output): void
    {
        $resolved = ConfigLoader::resolve(null, $basePath);
        $config = $resolved !== null ? ConfigLoader::load($resolved) : [];
        $ignoreSkills = (bool) ($config['skills']['auto_refresh'] ?? false);

        $status = (new GitignoreInstaller())->ensure($basePath, $ignoreSkills);

        match ($status) {
            GitignoreInstaller::STATUS_INSTALLED => $output->writeln('Created .gitignore with code-commandments state entries'),
            GitignoreInstaller::STATUS_APPENDED => $output->writeln('Added code-commandments state entries to .gitignore'),
            GitignoreInstaller::STATUS_UPDATED => $output->writeln('Refreshed code-commandments state entries in .gitignore'),
            GitignoreInstaller::STATUS_ALREADY_PRESENT => $output->writeln('.gitignore already ignores code-commandments state'),
            GitignoreInstaller::STATUS_WRITE_FAILED => $output->writeln('Failed to write .gitignore — check permissions.'),
        };
    }

    private function installCommitHook(string $basePath, bool $force, OutputInterface $output): void
    {
        $installer = new CommitHookInstaller();

        $pre = $installer->install($basePath, $force);

        match ($pre) {
            CommitHookInstaller::STATUS_INSTALLED => $output->writeln('Installed git pre-commit gate at .git/hooks/pre-commit'),
            CommitHookInstaller::STATUS_APPENDED => $output->writeln('Appended the pre-commit gate to your existing .git/hooks/pre-commit'),
            CommitHookInstaller::STATUS_ALREADY_PRESENT => $output->writeln('Pre-commit gate already installed (use --force to refresh it)'),
            CommitHookInstaller::STATUS_NOT_GIT => $output->writeln('Not a git repository — skipped the commit hooks.'),
            CommitHookInstaller::STATUS_WRITE_FAILED => $output->writeln('Failed to write .git/hooks/pre-commit — check permissions.'),
        };

        if ($pre === CommitHookInstaller::STATUS_NOT_GIT) {
            return;
        }

        $post = $installer->installPostCommit($basePath, $force);

        match ($post) {
            CommitHookInstaller::STATUS_INSTALLED => $output->writeln('Installed git post-commit reset at .git/hooks/post-commit'),
            CommitHookInstaller::STATUS_APPENDED => $output->writeln('Appended the post-commit reset to your existing .git/hooks/post-commit'),
            CommitHookInstaller::STATUS_ALREADY_PRESENT => $output->writeln('Post-commit reset already installed (use --force to refresh it)'),
            CommitHookInstaller::STATUS_NOT_GIT => null,
            CommitHookInstaller::STATUS_WRITE_FAILED => $output->writeln('Failed to write .git/hooks/post-commit — check permissions.'),
        };

        $msg = $installer->installCommitMsg($basePath, $force);

        match ($msg) {
            CommitHookInstaller::STATUS_INSTALLED => $output->writeln('Installed git commit-msg guard (rejects Co-authored-by) at .git/hooks/commit-msg'),
            CommitHookInstaller::STATUS_APPENDED => $output->writeln('Appended the commit-msg guard to your existing .git/hooks/commit-msg'),
            CommitHookInstaller::STATUS_ALREADY_PRESENT => $output->writeln('Commit-msg guard already installed (use --force to refresh it)'),
            CommitHookInstaller::STATUS_NOT_GIT => null,
            CommitHookInstaller::STATUS_WRITE_FAILED => $output->writeln('Failed to write .git/hooks/commit-msg — check permissions.'),
        };

        $push = $installer->installPrePush($basePath, $force);

        match ($push) {
            CommitHookInstaller::STATUS_INSTALLED => $output->writeln('Installed git pre-push reset (clears until-push absolutions) at .git/hooks/pre-push'),
            CommitHookInstaller::STATUS_APPENDED => $output->writeln('Appended the pre-push reset to your existing .git/hooks/pre-push'),
            CommitHookInstaller::STATUS_ALREADY_PRESENT => $output->writeln('Pre-push reset already installed (use --force to refresh it)'),
            CommitHookInstaller::STATUS_NOT_GIT => null,
            CommitHookInstaller::STATUS_WRITE_FAILED => $output->writeln('Failed to write .git/hooks/pre-push — check permissions.'),
        };
    }

    /**
     * A PostToolUse (Bash) hook command: when the tool call was a git commit,
     * inject a reminder into Claude's context to re-read the commandments and
     * resolve every sin before the next phase.
     */
    private function createConfig(string $basePath, bool $autoDetect, OutputInterface $output): void
    {
        $configPath = $basePath . '/commandments.php';

        // The config holds the project's scroll + prophet list — NEVER overwrite
        // an existing one, not even with --force (that flag refreshes the hooks
        // and CLAUDE.md, not your configuration). Clobbering it would wipe every
        // prophet you registered. To add newly-shipped prophets to an existing
        // config, use `sync`, not `init`.
        if (file_exists($configPath)) {
            $output->writeln('commandments.php already exists — left untouched (run `sync` to register new prophets).');

            return;
        }

        if ($autoDetect) {
            $this->createAutoDetectedConfig($basePath, $configPath, $output);

            return;
        }

        $distPath = $this->findDistFile();

        if ($distPath === null) {
            $output->writeln('Could not find commandments.php.dist template');

            return;
        }

        copy($distPath, $configPath);
        $output->writeln('Created commandments.php');
    }

    private function createAutoDetectedConfig(string $basePath, string $configPath, OutputInterface $output): void
    {
        $detector = new ProjectDetector();
        $projects = $detector->detect($basePath);

        if (empty($projects)) {
            $output->writeln('No projects detected. Falling back to template.');

            $distPath = $this->findDistFile();

            if ($distPath !== null) {
                copy($distPath, $configPath);
                $output->writeln('Created commandments.php');
            }

            return;
        }

        $output->writeln('Detected projects:');

        foreach ($projects as $project) {
            $types = [];

            if ($project->hasPhp) {
                $types[] = 'PHP (' . $project->phpSourcePath . '/)';
            }

            if ($project->hasFrontend) {
                $types[] = 'Frontend (' . $project->frontendSourcePath . '/)';
            }

            $output->writeln('  - ' . $project->name . ': ' . implode(', ', $types));
        }

        $generator = new ConfigGenerator();
        $content = $generator->generate($projects, $basePath);

        file_put_contents($configPath, $content);
        $output->writeln('Created commandments.php (auto-detected)');
    }

    private function findDistFile(): ?string
    {
        $paths = [
            __DIR__ . '/../../commandments.php.dist',           // Running from package source
            __DIR__ . '/../../../../commandments.php.dist',     // Installed as dependency (vendor/jessegall/code-commandments/src/Console)
        ];

        foreach ($paths as $path) {
            $real = realpath($path);
            if ($real !== false && file_exists($real)) {
                return $real;
            }
        }

        return null;
    }

    private function installHandoffHelper(string $basePath, OutputInterface $output): void
    {
        $status = HandoffHelper::install($basePath);

        $output->writeln($status === HandoffHelper::STATUS_INSTALLED
            ? 'Installed the handoff helper at .claude/hooks/handoff.sh'
            : 'Failed to write the handoff helper — check permissions.');
    }

    private function planLoopEnabled(string $basePath): bool
    {
        $resolved = ConfigLoader::resolve(null, $basePath);
        $config = $resolved !== null ? ConfigLoader::load($resolved) : [];

        return PlanLoopHookSuite::enabled($config);
    }

    private function installPlanLoopScripts(string $basePath, OutputInterface $output): void
    {
        if (! $this->planLoopEnabled($basePath)) {
            return;
        }

        $status = PlanLoopHookSuite::install($basePath);

        $output->writeln($status === PlanLoopHookSuite::STATUS_INSTALLED
            ? 'Installed the plan-loop hook scripts into .claude/hooks/'
            : 'Failed to write the plan-loop hook scripts — check permissions.');
    }

    private function createClaudeHooks(string $basePath, bool $force, OutputInterface $output): void
    {
        $claudeDir = $basePath . '/.claude';
        $settingsFile = $claudeDir . '/settings.json';

        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
        }

        $existingSettings = [];
        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            $existingSettings = json_decode($content ?: T_Json::emptyObject(), true) ?? [];
        }

        // Reconcile to the CURRENT package wiring: replace every package-owned
        // entry with the latest set while preserving the consumer's custom hooks.
        // Shared with install-hooks + sync via ClaudeHooksInstaller so the artisan
        // and vendor/bin wirings can never drift, and an update always lands the
        // newest wiring.
        $hooks = ClaudeHooksInstaller::apply(
            $existingSettings['hooks'] ?? [],
            $basePath,
            $this->planLoopEnabled($basePath),
        );

        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = ClaudeMdInstaller::settingsInstructions($basePath);
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($settingsFile, $json . T_String::NEWLINE);
        $output->writeln('Created .claude/settings.json with hooks');
    }

    private function createClaudeMd(string $basePath, OutputInterface $output): void
    {
        // Shared with install-hooks + sync via ClaudeMdInstaller: a sentinel-fenced
        // section, spliced (never preg_replace), runner-parameterized so it can't drift.
        match (ClaudeMdInstaller::install($basePath)) {
            ClaudeMdInstaller::STATUS_CREATED => $output->writeln('Created CLAUDE.md'),
            ClaudeMdInstaller::STATUS_APPENDED => $output->writeln('Added Code Commandments section to CLAUDE.md'),
            ClaudeMdInstaller::STATUS_REPLACED => $output->writeln('Updated CLAUDE.md'),
            ClaudeMdInstaller::STATUS_SKIPPED_CONFLICT => $output->writeln('<comment>CLAUDE.md has merge conflict markers — skipped the Code Commandments section.</comment>'),
            ClaudeMdInstaller::STATUS_WRITE_FAILED => $output->writeln('<error>Failed to write CLAUDE.md — check permissions.</error>'),
            default => null,
        };
    }
}
