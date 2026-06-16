<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\CommitHookInstaller;
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
        $this->installCommitHook($basePath, $force, $output);

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
    }

    /**
     * A PostToolUse (Bash) hook command: when the tool call was a git commit,
     * inject a reminder into Claude's context to re-read the commandments and
     * resolve every sin before the next phase.
     */
    private function postCommitReminderCommand(): string
    {
        $message = 'A commit just landed — a phase is complete. Re-read the Code Commandments '
            . 'section of CLAUDE.md now and act as a sin resolver: run `vendor/bin/commandments judge --next --git` '
            . 'and handle every finding before starting the next phase. Fix each sin — even pre-existing ones in '
            . 'files you touched. Warnings: default to FIXING; absolve only when the rubric LEAVE-WHEN genuinely '
            . 'applies, with a reason. Absolve is not a dismiss button. I did not cause this is never a reason to '
            . 'leave a sin in place.';

        $json = '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"' . $message . '"}}';

        return 'in=$(cat); printf "%s" "$in" | grep -q "git commit" && printf '
            . escapeshellarg($json) . '; exit 0';
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
        }

        $ourHooks = [
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
            'PostToolUse' => [
                [
                    'matcher' => 'Bash',
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => $this->postCommitReminderCommand(),
                        ],
                    ],
                ],
            ],
        ];

        // Merge our events into any existing hooks (overwrite only our events,
        // preserve the user's other hooks; idempotent across re-runs).
        $hooks = array_merge($existingSettings['hooks'] ?? [], $ourHooks);

        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = <<<'INSTRUCTIONS'
This project uses Code Commandments to enforce coding standards.

IMPORTANT: Never commit code with sins. The git pre-commit hook will BLOCK
any commit while sins remain.

THE GUIDED WORKFLOW (use this): run `vendor/bin/commandments judge --next --git`.
Scope to YOUR changes with --git so you are not handed the repo's pre-existing
backlog (plain `--next` walks the whole codebase). To accept a large
pre-existing backlog once so only NEW findings surface, run
`vendor/bin/commandments absolve --all --reason="accept backlog"`.
It shows exactly ONE finding at a time with its full rule inline, so you
cannot miss anything in a wall of output. For each finding do exactly one:
  - Fix it, then run `judge --next` again for the next one; OR
  - If it is an advisory WARNING whose rubric does not apply here, absolve it
    WITH A REASON: `vendor/bin/commandments absolve --fingerprint=<hash> --reason="…"`.
Sins are imperative and cannot be absolved — they must be fixed.

OWN EVERY SIN YOU ENCOUNTER: a sin is a sin regardless of who wrote it. If
judge surfaces a sin — in your own changes OR pre-existing in a file you are
working in — you handle it. Fix it (sins cannot be absolved), or for an
advisory warning whose rubric genuinely does not apply, absolve it with a
reason. "I didn't cause this" is NEVER a reason to leave a finding in place.
Be a gentleman: leave every file you touch righteous.

REQUIRED: Always read the rule before fixing. `judge --next` prints the
rubric inline; for the full scripture run
`vendor/bin/commandments scripture --prophet=NAME`. Warnings are ADVISORY —
each carries an APPLY-WHEN / LEAVE-WHEN rubric. Use judgment, but never leave
one untouched: fix or absolve every one.

PHASED-COMMIT WORKFLOW (for any multi-step change, all in ONE pull request):
  1. Implement ONE phase.
  2. Run `vendor/bin/commandments judge --git`, then `--next` until clean —
     fix every sin (and address each warning).
  3. Commit and push that phase.
  4. Move to the next phase and repeat.
This keeps every commit righteous and each phase reviewable on its own.

FALSE POSITIVE? If a finding is genuinely wrong (a true false-positive, or a
rule that does not fit), do NOT just absolve and move on — REPORT it so the
prophet gets fixed:
  vendor/bin/commandments report --prophet=NAME --file=PATH --line=N --reason="why it is wrong"
This files a GitHub issue another session can pick up and fix.

COMMANDS:
  vendor/bin/commandments judge --git        # Check changed files
  vendor/bin/commandments judge --next       # GUIDED: one finding at a time
  vendor/bin/commandments absolve --fingerprint=H --reason="…"  # warnings only
  vendor/bin/commandments repent             # Auto-fix where possible
  vendor/bin/commandments report --prophet=NAME --reason="…"  # report a false positive
  vendor/bin/commandments scripture --prophet=NAME  # Full rule for a prophet
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

**IMPORTANT: Never commit code with sins. A git pre-commit hook will BLOCK any commit while sins remain.**

**REQUIRED: Always read the rule before fixing. `judge --next` shows the rubric inline; `commandments scripture --prophet=NAME` shows the full scripture. The detailed description is the authoritative specification — follow it exactly.**

### The guided workflow (use this)

```bash
vendor/bin/commandments judge --next --git   # walk findings in YOUR changes
```

**Scope to your own changes with `--git`** so you are not handed the whole repo's pre-existing backlog. (Plain `judge --next` walks the entire codebase.) If a large pre-existing backlog is in the way, baseline it once — this absolves every current advisory finding so only NEW ones surface (sins still block):

```bash
vendor/bin/commandments absolve --all --reason="accept pre-existing backlog"
```

It shows exactly **one finding at a time** with its full rule inline — so nothing gets lost in a wall of output. For each finding, do exactly one of:

- **Fix it**, then run `judge --next` again for the next finding; or
- If it is an advisory **warning** whose rubric does not apply here, **absolve it with a reason**:
  `vendor/bin/commandments absolve --fingerprint=<hash> --reason="why it does not apply"`.

Sins are imperative and **cannot be absolved** — they must be fixed. Warnings are **advisory**: each carries an APPLY-WHEN / LEAVE-WHEN rubric. **Default to FIXING a warning.** Absolve it only when the rubric's LEAVE-WHEN genuinely applies, and say why — absolve is not a dismiss button, and the post-commit reset wipes absolutions anyway, so a dodged warning comes back next phase. Never leave a warning untouched.

### Own every sin you encounter

A sin is a sin regardless of who wrote it. If `judge` surfaces a sin — whether in your own changes or **pre-existing** in a file you are working in — **you handle it**: fix it (sins cannot be absolved), or for an advisory warning whose rubric genuinely does not apply, absolve it with a reason. **"I didn't cause this" is never a reason to leave a finding in place.** Be a gentleman: leave every file you touch righteous.

### Phased-commit workflow (multi-step changes, one PR)

1. Implement **one phase**.
2. Run `commandments judge --git`, then `--next` until clean — fix every sin and address each warning.
3. **Commit and push** that phase.
4. Move to the next phase and repeat.

Every commit stays righteous and each phase is reviewable on its own.

### Commands

```bash
vendor/bin/commandments judge --git        # Check changed files
vendor/bin/commandments judge --next       # GUIDED: one finding at a time
vendor/bin/commandments absolve --fingerprint=H --reason="…"  # warnings only
vendor/bin/commandments repent             # Auto-fix [AUTO-FIXABLE] sins
vendor/bin/commandments report --prophet=NAME --reason="…"  # Report a false positive
vendor/bin/commandments scripture --prophet=NAME  # Full rule for a prophet
```

**See a genuine false positive (or a rule that does not fit)?** Don't just absolve and move on — `commandments report --prophet=NAME --file=PATH --line=N --reason="why"` files a GitHub issue so the prophet gets fixed.
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
