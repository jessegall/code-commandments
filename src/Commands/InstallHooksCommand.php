<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;

/**
 * Install Claude Code hooks for the commandments.
 *
 * Sets up hooks that will display the scripture on session start
 * and judge the codebase after Claude completes work.
 */
class InstallHooksCommand extends Command
{
    protected $signature = 'commandments:install-hooks
        {--force : Overwrite existing hooks configuration}';

    protected $description = 'Install Claude Code hooks for code commandments';

    public function handle(): int
    {
        $this->output->writeln('<fg=yellow>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║           INSTALLING CLAUDE CODE HOOKS                    ║');
        $this->output->writeln('  ║      The prophets shall guide Claude\'s every move         ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->newLine();

        $claudeDir = base_path('.claude');
        $settingsFile = $claudeDir.'/settings.json';

        // Create .claude directory if it doesn't exist
        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
            $this->info('Created .claude directory');
        }

        // Check for existing settings
        $existingSettings = [];
        if (file_exists($settingsFile)) {
            if (!$this->option('force')) {
                $content = file_get_contents($settingsFile);
                $existingSettings = json_decode($content ?: '{}', true) ?? [];

                if (isset($existingSettings['hooks'])) {
                    $this->warn('Existing hooks found in .claude/settings.json');
                    if (!$this->confirm('Do you want to merge the commandments hooks?', true)) {
                        $this->info('Installation cancelled.');
                        return self::SUCCESS;
                    }
                }
            } else {
                $content = file_get_contents($settingsFile);
                $existingSettings = json_decode($content ?: '{}', true) ?? [];
            }
        }

        // Build the hooks configuration
        $hooks = $this->buildHooksConfig();

        // Merge with existing settings
        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        // Also add CLAUDE.md instructions
        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = $this->getClaudeInstructions();
        }

        // Write settings file
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($settingsFile, $json."\n");

        $this->info('✓ Claude Code hooks installed');
        $this->newLine();

        // Create/update CLAUDE.md
        $this->createClaudeMd();

        $this->output->writeln('<fg=green>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║                  INSTALLATION COMPLETE                    ║');
        $this->output->writeln('  ║                                                           ║');
        $this->output->writeln('  ║  The hooks will:                                          ║');
        $this->output->writeln('  ║  • Show scripture on Claude Code session start            ║');
        $this->output->writeln('  ║  • Judge code after Claude completes work                 ║');
        $this->output->writeln('  ║  • Show detailed guidance for any violations              ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');

        return self::SUCCESS;
    }

    /**
     * Build the Claude Code hooks configuration.
     */
    private function buildHooksConfig(): array
    {
        return [
            // Run scripture on session start
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
            // Run judge after Claude stops (completes a response)
            'Stop' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => $this->getJudgeHookScript(),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the judge hook script that checks for sins and instructs Claude.
     */
    private function getJudgeHookScript(): string
    {
        // This script runs the judge command and provides clear instructions
        // distinguishing between sins (must fix) and warnings (review/absolve)
        return <<<'BASH'
OUTPUT=$(php artisan commandments:judge --summary 2>/dev/null); EXIT_CODE=$?; echo "$OUTPUT"; if echo "$OUTPUT" | grep -q "sins found"; then echo ""; echo "🚨 SINS FOUND - These MUST be fixed:"; echo "   1. Run 'php artisan commandments:judge' for details"; echo "   2. Run 'php artisan commandments:repent' to auto-fix"; echo "   3. Manually fix remaining sins"; echo ""; fi; if echo "$OUTPUT" | grep -q "manual verification"; then echo ""; echo "📋 MANUAL VERIFICATION needed - Review and decide:"; echo "   1. Run 'php artisan commandments:judge' to see the warnings"; echo "   2. Review each file - is the code acceptable as-is?"; echo "   3. If acceptable: Run 'php artisan commandments:judge --absolve' to mark as reviewed"; echo "   4. If needs changes: Fix the code"; echo ""; fi; exit 0
BASH;
    }

    /**
     * Get Claude instructions for the settings file.
     */
    private function getClaudeInstructions(): string
    {
        return <<<'INSTRUCTIONS'
This project uses the Code Commandments package to enforce coding standards.

AFTER ANY CODE CHANGES, handle issues based on type:

🔴 SINS (red ✗) - MUST be fixed:
   1. Run `php artisan commandments:judge` for details
   2. Run `php artisan commandments:repent` to auto-fix
   3. Manually fix remaining sins
   4. Re-run judge until sins are gone

🟡 WARNINGS (yellow ⚠️) - Review and decide:
   1. Run `php artisan commandments:judge` to see warnings
   2. Review each warning - is the code acceptable?
   3. If acceptable: Run `php artisan commandments:judge --absolve` to mark as reviewed
   4. If needs fixing: Fix the code

Task is complete when: No sins remain, and warnings are either fixed or absolved.
INSTRUCTIONS;
    }

    /**
     * Create or update the CLAUDE.md file with commandments instructions.
     */
    private function createClaudeMd(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');
        $commandmentsSection = $this->getClaudeMdSection();

        if (file_exists($claudeMdPath)) {
            $content = file_get_contents($claudeMdPath);

            // Check if commandments section already exists
            if (str_contains($content, '## Code Commandments')) {
                $this->info('CLAUDE.md already contains Code Commandments section');
                return;
            }

            // Append to existing CLAUDE.md
            $content .= "\n\n".$commandmentsSection;
            file_put_contents($claudeMdPath, $content);
            $this->info('✓ Updated CLAUDE.md with Code Commandments section');
        } else {
            // Create new CLAUDE.md
            file_put_contents($claudeMdPath, $commandmentsSection);
            $this->info('✓ Created CLAUDE.md with Code Commandments instructions');
        }
    }

    /**
     * Get the CLAUDE.md section for code commandments.
     */
    private function getClaudeMdSection(): string
    {
        return <<<'MARKDOWN'
## Code Commandments

This project enforces coding standards through the **Code Commandments** package.

### Two Types of Issues

| Type | Symbol | Action Required |
|------|--------|-----------------|
| **Sins** (violations) | 🔴 ✗ | MUST be fixed - no exceptions |
| **Warnings** (manual validation) | 🟡 ⚠️ | Review and decide - fix OR absolve |

### 🔴 When SINS Are Found

Sins are definite violations that MUST be fixed:

1. Run `php artisan commandments:judge` to see full details with explanations
2. Run `php artisan commandments:repent` to auto-fix what's possible
3. Manually fix any remaining sins following the detailed guidance
4. Re-run judge until no sins remain

### 🟡 When WARNINGS Are Found (Manual Verification)

Warnings are potential issues that require your judgment:

1. Run `php artisan commandments:judge` to see the warnings
2. **Review each warning** - read the code and the suggestion
3. **Make a decision**:
   - If the code is **acceptable as-is** → Run `php artisan commandments:judge --absolve` to mark as reviewed
   - If the code **needs improvement** → Fix it according to the suggestion
4. Absolving means "I reviewed this and it's fine" - use when the pattern is intentional

### Available Commands

| Command | Purpose |
|---------|---------|
| `php artisan commandments:scripture` | List all commandments |
| `php artisan commandments:scripture --detailed` | Show full explanations with examples |
| `php artisan commandments:judge` | Check code - shows sins AND warnings |
| `php artisan commandments:judge --absolve` | Mark warnings as reviewed (absolved) |
| `php artisan commandments:repent` | Auto-fix sins where possible |

### Example Workflows

**Sins found:**
```bash
php artisan commandments:judge          # See the sins
php artisan commandments:repent         # Auto-fix
# Manually fix remaining
php artisan commandments:judge          # Verify clean
```

**Warnings found (manual validation):**
```bash
php artisan commandments:judge          # See the warnings
# Review each file mentioned...
# If code is fine as-is:
php artisan commandments:judge --absolve
# If code needs changes: fix it, then re-run judge
```
MARKDOWN;
    }
}
