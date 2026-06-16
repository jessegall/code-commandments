<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\CommitHookInstaller;

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

        // Install the git pre-commit gate that hard-blocks commits with sins.
        $this->installCommitHook();

        $this->output->newLine();
        $this->output->writeln('Hooks will:');
        $this->output->writeln('- Show commandments on session start');
        $this->output->writeln('- Judge changed code after Claude completes work');
        $this->output->writeln('- Block git commits while any sins remain (pre-commit hook)');

        return self::SUCCESS;
    }

    private function installCommitHook(): void
    {
        $status = (new CommitHookInstaller())->install(base_path(), (bool) $this->option('force'));

        match ($status) {
            CommitHookInstaller::STATUS_INSTALLED => $this->output->writeln('Installed git pre-commit gate at .git/hooks/pre-commit'),
            CommitHookInstaller::STATUS_APPENDED => $this->output->writeln('Appended the pre-commit gate to your existing .git/hooks/pre-commit'),
            CommitHookInstaller::STATUS_ALREADY_PRESENT => $this->output->writeln('Pre-commit gate already installed — use --force to refresh it'),
            CommitHookInstaller::STATUS_NOT_GIT => $this->warn('Not a git repository — skipped the pre-commit gate.'),
            CommitHookInstaller::STATUS_WRITE_FAILED => $this->error('Failed to write .git/hooks/pre-commit — check permissions.'),
        };
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

IMPORTANT: Never commit code with sins. The git pre-commit hook will BLOCK
any commit while sins remain.

THE GUIDED WORKFLOW (use this): run `php artisan commandments:judge --next --git`.
Scope to YOUR changes with --git so you are not handed the repo's pre-existing
backlog (plain `--next` walks the whole codebase). To accept a large
pre-existing backlog once so only NEW findings surface, run
`php artisan commandments:absolve --all --reason="accept backlog"`.
It shows exactly ONE finding at a time with its full rule inline, so you
cannot miss anything in a wall of output. For each finding do exactly one:
  - Fix it, then run `judge --next` again for the next one; OR
  - If it is an advisory WARNING whose rubric does not apply here, absolve it
    WITH A REASON: `php artisan commandments:absolve --fingerprint=<hash> --reason="…"`.
Sins are imperative and cannot be absolved — they must be fixed.

OWN EVERY SIN YOU ENCOUNTER: a sin is a sin regardless of who wrote it. If
judge surfaces a sin — in your own changes OR pre-existing in a file you are
working in — you handle it. Fix it (sins cannot be absolved), or for an
advisory warning whose rubric genuinely does not apply, absolve it with a
reason. "I didn't cause this" is NEVER a reason to leave a finding in place.
Be a gentleman: leave every file you touch righteous.

REQUIRED: Always read the rule before fixing. `judge --next` prints the
rubric inline; for the full scripture run
`php artisan commandments:scripture --prophet=NAME`. Warnings are ADVISORY —
each carries an APPLY-WHEN / LEAVE-WHEN rubric. Use judgment, but never leave
one untouched: fix or absolve every one.

PHASED-COMMIT WORKFLOW (for any multi-step change, all in ONE pull request):
  1. Implement ONE phase.
  2. Run `php artisan commandments:judge --git`, then `--next` until clean —
     fix every sin (and address each warning).
  3. Commit and push that phase.
  4. Move to the next phase and repeat.
This keeps every commit righteous and each phase reviewable on its own.

COMMANDS:
  php artisan commandments:judge --git        # Check changed files
  php artisan commandments:judge --next       # GUIDED: one finding at a time
  php artisan commandments:absolve --fingerprint=H --reason="…"  # warnings only
  php artisan commandments:repent             # Auto-fix where possible
  php artisan commandments:scripture --prophet=NAME  # Full rule for a prophet
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

**IMPORTANT: Never commit code with sins. A git pre-commit hook will BLOCK any commit while sins remain.**

**REQUIRED: Always read the rule before fixing. `judge --next` shows the rubric inline; `commandments:scripture --prophet=NAME` shows the full scripture. The detailed description is the authoritative specification — follow it exactly.**

### The guided workflow (use this)

```bash
php artisan commandments:judge --next --git   # walk findings in YOUR changes
```

**Scope to your own changes with `--git`** so you are not handed the whole repo's pre-existing backlog. (Plain `judge --next` walks the entire codebase.) If a large pre-existing backlog is in the way, baseline it once — this absolves every current advisory finding so only NEW ones surface (sins still block):

```bash
php artisan commandments:absolve --all --reason="accept pre-existing backlog"
```

It shows exactly **one finding at a time** with its full rule inline — so nothing gets lost in a wall of output. For each finding, do exactly one of:

- **Fix it**, then run `judge --next` again for the next finding; or
- If it is an advisory **warning** whose rubric does not apply here, **absolve it with a reason**:
  `php artisan commandments:absolve --fingerprint=<hash> --reason="why it does not apply"`.

Sins are imperative and **cannot be absolved** — they must be fixed. Warnings are **advisory**: each carries an APPLY-WHEN / LEAVE-WHEN rubric. Use judgment, but never leave one untouched — fix or absolve every one.

### Own every sin you encounter

A sin is a sin regardless of who wrote it. If `judge` surfaces a sin — whether in your own changes or **pre-existing** in a file you are working in — **you handle it**: fix it (sins cannot be absolved), or for an advisory warning whose rubric genuinely does not apply, absolve it with a reason. **"I didn't cause this" is never a reason to leave a finding in place.** Be a gentleman: leave every file you touch righteous.

### Phased-commit workflow (multi-step changes, one PR)

1. Implement **one phase**.
2. Run `commandments:judge --git`, then `--next` until clean — fix every sin and address each warning.
3. **Commit and push** that phase.
4. Move to the next phase and repeat.

Every commit stays righteous and each phase is reviewable on its own.

### Commands

```bash
php artisan commandments:judge --git        # Check changed files
php artisan commandments:judge --next       # GUIDED: one finding at a time
php artisan commandments:absolve --fingerprint=H --reason="…"  # warnings only
php artisan commandments:repent             # Auto-fix [AUTO-FIXABLE] sins
php artisan commandments:scripture --prophet=NAME  # Full rule for a prophet
```
MARKDOWN;
    }
}
