<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;

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
            $existingSettings = json_decode($content ?: '{}', true) ?? [];

            if (!$this->option('force') && isset($existingSettings['hooks'])) {
                $this->output->writeln('Existing hooks found. Use --force to overwrite.');
                return self::SUCCESS;
            }
        }

        // Build the hooks configuration
        $hooks = $this->buildHooksConfig();

        // Merge with existing settings
        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        // Add instructions if not present
        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = $this->getClaudeInstructions();
        }

        // Write settings file
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($settingsFile, $json."\n");

        $this->output->writeln('Hooks installed to .claude/settings.json');

        // Create/update CLAUDE.md
        $this->createClaudeMd();

        $this->output->newLine();
        $this->output->writeln('Hooks will:');
        $this->output->writeln('- Show commandments on session start');
        $this->output->writeln('- Judge code after Claude completes work');

        return self::SUCCESS;
    }

    /**
     * Build the Claude Code hooks configuration.
     */
    private function buildHooksConfig(): array
    {
        return [
            'SessionStart' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => 'php artisan commandments:scripture 2>/dev/null || true',
                        ],
                    ],
                ],
            ],
            'Stop' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => 'php artisan commandments:judge --git 2>/dev/null; exit 0',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Claude instructions for the settings file.
     */
    private function getClaudeInstructions(): string
    {
        return <<<'INSTRUCTIONS'
This project uses Code Commandments to enforce coding standards.

IMPORTANT: Never commit code with sins. Fix all violations first.

COMMANDS:
  php artisan commandments:judge          # Check for violations
  php artisan commandments:repent         # Auto-fix where possible
  php artisan commandments:scripture      # List all rules

Use --files=a.php,b.php to target specific files.
INSTRUCTIONS;
    }

    /**
     * Create or update the CLAUDE.md file.
     */
    private function createClaudeMd(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');
        $section = $this->getClaudeMdSection();

        if (file_exists($claudeMdPath)) {
            $content = file_get_contents($claudeMdPath);

            if (str_contains($content, '## Code Commandments')) {
                $pattern = '/(## Code Commandments\s*)(.+?)(?=\n## (?!Code Commandments)|\z)/s';
                $content = preg_replace($pattern, $section, $content);
                file_put_contents($claudeMdPath, $content);
                $this->output->writeln('Updated CLAUDE.md');
                return;
            }

            $content .= "\n\n".$section;
            file_put_contents($claudeMdPath, $content);
            $this->output->writeln('Added section to CLAUDE.md');
        } else {
            file_put_contents($claudeMdPath, $section);
            $this->output->writeln('Created CLAUDE.md');
        }
    }

    /**
     * Get the CLAUDE.md section for code commandments.
     */
    private function getClaudeMdSection(): string
    {
        return <<<'MARKDOWN'
## Code Commandments

This project enforces coding standards via the Code Commandments package.

**IMPORTANT: Never commit code with sins. Fix all violations first.**

### Commands

```bash
php artisan commandments:judge              # Check for violations
php artisan commandments:judge --git        # Check only changed files
php artisan commandments:repent             # Auto-fix [AUTO-FIXABLE] sins
php artisan commandments:scripture          # List all rules
```

Use `--files=a.php,b.php` to target specific files.

### Workflow

1. Write code
2. Run `commandments:judge` - see violations
3. Run `commandments:repent` - auto-fix what's possible
4. Manually fix remaining sins
5. Re-run judge until clean, then commit
MARKDOWN;
    }
}
