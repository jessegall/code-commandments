<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\ConfigGenerator;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Support\PlanLoopHookSuite;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
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
        $this->seedSettingsInstructions($basePath);

        // init IS `profile phased`: the profile owns the Claude hook wiring, the
        // git hooks, and the CLAUDE.md section, and records .commandments/profile.
        (new ProfileService($basePath, $this->loadConfig($basePath)))->switch(
            'phased',
            $output->writeln(...),
            fn (string $line) => $output->writeln('<comment>' . $line . '</comment>'),
        );

        $this->installSkills($basePath, $force, $output);
        $this->installHandoffHelper($basePath, $output);
        $this->installPlanLoopScripts($basePath, $output);
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
            $output->writeln(...),
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

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $basePath): array
    {
        $resolved = ConfigLoader::resolve(null, $basePath);

        return $resolved !== null ? ConfigLoader::load($resolved) : [];
    }

    /**
     * Seed the settings.json `instructions` block before the profile switch merges
     * its hooks into the file. Only added when absent.
     */
    private function seedSettingsInstructions(string $basePath): void
    {
        $claudeDir = $basePath . '/.claude';
        $settingsFile = $claudeDir . '/settings.json';

        if (! is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
        }

        $settings = [];
        if (file_exists($settingsFile)) {
            $settings = json_decode((string) file_get_contents($settingsFile) ?: T_Json::emptyObject(), true) ?? [];
        }

        if (isset($settings['instructions'])) {
            return;
        }

        $settings['instructions'] = ClaudeMdInstaller::settingsInstructions($basePath);
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . T_String::NEWLINE);
    }

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

}
