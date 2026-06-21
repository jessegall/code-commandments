<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\VersionResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

/**
 * Add newly available prophets to an existing config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('sync')
            ->setDescription('Add newly available prophets to your config file')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to commandments.php config file')
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Only add prophets introduced after this version (e.g. 1.4.0). Pass `previous` to use the last synced version automatically. Prophets you removed before upgrading stay removed.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be added without modifying the file');
    }

    private function autoScaffold(string $configPath, string $basePath, OutputInterface $output): void
    {
        $scaffold = (ConfigLoader::load($configPath)['scaffold'] ?? []);

        if (($scaffold['auto'] ?? true) === false) {
            return;
        }

        $results = \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator::packaged()->generate(
            $scaffold['namespace'] ?? 'App\\Support',
            $scaffold['path'] ?? ($basePath . '/app/Support'),
            false,
            $scaffold['except'] ?? [],
        );

        $created = \JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter::report(
            $results,
            fn (string $line) => $output->writeln($line),
        );

        if ($created > 0) {
            $output->writeln("<info>Generated {$created} new support class(es).</info>");
        }
    }

    private function autoSkills(string $configPath, string $basePath, OutputInterface $output): void
    {
        $config = ConfigLoader::load($configPath);
        $skills = $config['skills'] ?? [];

        if (($skills['auto'] ?? true) === false) {
            return;
        }

        $autoRefresh = (bool) ($skills['auto_refresh'] ?? false);

        $results = \JesseGall\CodeCommandments\Support\Skills\SkillInstaller::packaged()->install(
            $config['scaffold']['namespace'] ?? 'App\\Support',
            $basePath . '/.claude/skills',
            $autoRefresh,
            $skills['except'] ?? [],
            $autoRefresh,
        );

        $installed = \JesseGall\CodeCommandments\Support\Skills\SkillReporter::report(
            $results,
            fn (string $line) => $output->writeln($line),
        );

        if ($installed > 0) {
            $output->writeln("<info>Installed {$installed} new skill(s).</info>");
        }
    }

    /**
     * Keep the generated tracking state out of version control. Runs on every
     * sync — including the automatic post-merge sync after a package update —
     * so a consumer's .gitignore picks up newly-tracked state files. Stays
     * quiet when nothing changed to avoid noise on routine updates.
     */
    private function ensureGitignore(string $configPath, string $basePath, OutputInterface $output): void
    {
        $config = ConfigLoader::load($configPath);
        $ignoreSkills = (bool) ($config['skills']['auto_refresh'] ?? false);

        $status = (new GitignoreInstaller())->ensure($basePath, $ignoreSkills);

        match ($status) {
            GitignoreInstaller::STATUS_INSTALLED => $output->writeln('Created .gitignore with code-commandments state entries'),
            GitignoreInstaller::STATUS_APPENDED => $output->writeln('Added code-commandments state entries to .gitignore'),
            GitignoreInstaller::STATUS_UPDATED => $output->writeln('Refreshed code-commandments state entries in .gitignore'),
            GitignoreInstaller::STATUS_WRITE_FAILED => $output->writeln('<comment>Failed to write .gitignore — check permissions.</comment>'),
            GitignoreInstaller::STATUS_ALREADY_PRESENT => null,
        };
    }

    /**
     * Refresh the opt-in plan-loop hook scripts on upgrade (when enabled in
     * config). Mirrors the artisan SyncCommand; only refreshes the scripts, not
     * the settings.json wiring (that is init's job).
     */
    private function syncPlanLoopScripts(string $configPath, string $basePath, OutputInterface $output): void
    {
        $config = ConfigLoader::load($configPath);

        if (! \JesseGall\CodeCommandments\Support\PlanLoopHookSuite::enabled($config)) {
            return;
        }

        if (\JesseGall\CodeCommandments\Support\PlanLoopHookSuite::install($basePath) === \JesseGall\CodeCommandments\Support\PlanLoopHookSuite::STATUS_INSTALLED) {
            $output->writeln('Refreshed the plan-loop hook scripts in .claude/hooks/');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);

        $configPath = ConfigLoader::resolve($input->getOption('config'), $basePath);

        if ($configPath === null) {
            $output->writeln('No configuration file found. Run "commandments init" first.');

            return Command::FAILURE;
        }

        if (! $input->getOption('dry-run')) {
            $this->autoScaffold($configPath, $basePath, $output);
            $this->autoSkills($configPath, $basePath, $output);
            $this->ensureGitignore($configPath, $basePath, $output);
            $this->syncPlanLoopScripts($configPath, $basePath, $output);
        }

        $after = $input->getOption('after');
        $versionResolver = new VersionResolver();

        if ($after === 'previous') {
            $after = $versionResolver->previousSyncedVersion($basePath);

            if ($after === null) {
                $output->writeln('<comment>No previous sync recorded — falling back to a full sync.</comment>');
            } else {
                $output->writeln("Using previous synced version: {$after}");
            }
        }

        if ($after !== null && ! $this->isValidVersion($after)) {
            $output->writeln("<error>--after must be a valid semver string (got: {$after})</error>");

            return Command::FAILURE;
        }

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath, $after);

        if (empty($result['added'])) {
            $message = $after !== null
                ? "No prophets introduced after {$after}. Nothing to sync."
                : 'All prophets are already registered. Nothing to sync.';
            $output->writeln($message);

            return Command::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $versionTag = $entry['introduced_in'] !== null
                ? " (introduced in {$entry['introduced_in']})"
                : T_String::empty();
            $output->writeln("  + {$shortName} → {$entry['scroll']}{$versionTag}");
        }

        $count = count($result['added']);

        if ($input->getOption('dry-run')) {
            $output->writeln(T_String::empty());
            $output->writeln("{$count} new prophet(s) found (dry run, no changes made).");

            return Command::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $currentVersion = $versionResolver->currentVersion();

        if ($currentVersion !== null) {
            $versionResolver->recordSyncedVersion($basePath, $currentVersion);
        }

        $relativePath = str_starts_with($configPath, $basePath)
            ? ltrim(str_replace($basePath, T_String::empty(), $configPath), '/')
            : $configPath;

        $output->writeln(T_String::empty());
        $output->writeln("Synced {$count} new prophet(s) into {$relativePath}.");

        if ($currentVersion !== null) {
            $output->writeln("Recorded sync version {$currentVersion} in .commandments-last-synced");
        }

        return Command::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
