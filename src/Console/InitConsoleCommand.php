<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigGenerator;
use JesseGall\CodeCommandments\Support\ProjectDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

        $this->createConfig($basePath, $force, $autoDetect, $output);
        $this->createClaudeHooks($basePath, $force, $output);
        $this->createClaudeMd($basePath, $output);

        $output->writeln('');
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

    private function createConfig(string $basePath, bool $force, bool $autoDetect, OutputInterface $output): void
    {
        $configPath = $basePath . '/commandments.php';

        if (file_exists($configPath) && !$force) {
            $output->writeln('commandments.php already exists (use --force to overwrite)');

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
            $existingSettings = json_decode($content ?: '{}', true) ?? [];

            if (!$force && isset($existingSettings['hooks'])) {
                $output->writeln('.claude/settings.json hooks already exist (use --force to overwrite)');

                return;
            }
        }

        $hooks = [
            'SessionStart' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments scripture 2>/dev/null || true',
                        ],
                    ],
                ],
            ],
            'Stop' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments judge --git 2>/dev/null; exit 0',
                        ],
                    ],
                ],
            ],
        ];

        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = <<<'INSTRUCTIONS'
This project uses Code Commandments to enforce coding standards.

IMPORTANT: Never commit code with sins. Fix all violations first.

COMMANDS:
  vendor/bin/commandments judge              # Check for violations
  vendor/bin/commandments repent             # Auto-fix where possible
  vendor/bin/commandments scripture          # List all rules

Use --files=a.php,b.php to target specific files.
INSTRUCTIONS;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($settingsFile, $json . "\n");
        $output->writeln('Created .claude/settings.json with hooks');
    }

    private function createClaudeMd(string $basePath, OutputInterface $output): void
    {
        $claudeMdPath = $basePath . '/CLAUDE.md';
        $section = <<<'MARKDOWN'
## Code Commandments

This project enforces coding standards via the Code Commandments package.

**IMPORTANT: Never commit code with sins. Fix all violations first.**

### Commands

```bash
vendor/bin/commandments judge              # Check for violations
vendor/bin/commandments judge --git        # Check only changed files
vendor/bin/commandments repent             # Auto-fix [AUTO-FIXABLE] sins
vendor/bin/commandments scripture          # List all rules
```

Use `--files=a.php,b.php` to target specific files.

### Workflow

1. Write code
2. Run `commandments judge` - see violations
3. Run `commandments repent` - auto-fix what's possible
4. Manually fix remaining sins
5. Re-run judge until clean, then commit
MARKDOWN;

        if (file_exists($claudeMdPath)) {
            $content = file_get_contents($claudeMdPath);

            if (str_contains($content, '## Code Commandments')) {
                $pattern = '/(## Code Commandments\s*)(.+?)(?=\n## (?!Code Commandments)|\z)/s';
                $content = preg_replace($pattern, $section, $content);
                file_put_contents($claudeMdPath, $content);
                $output->writeln('Updated CLAUDE.md');

                return;
            }

            $content .= "\n\n" . $section;
            file_put_contents($claudeMdPath, $content);
            $output->writeln('Added Code Commandments section to CLAUDE.md');
        } else {
            file_put_contents($claudeMdPath, $section);
            $output->writeln('Created CLAUDE.md');
        }
    }
}
