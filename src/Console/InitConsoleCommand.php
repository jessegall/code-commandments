<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use JesseGall\CodeCommandments\Support\ConfigGenerator;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Support\HookConfigMerger;
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

        $ourHooks = [
            'SessionStart' => [
                [
                    'hooks' => [
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments scripture 2>/dev/null || true',
                        ],
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments reports --check 2>/dev/null || true',
                        ],
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments scaffold --auto 2>/dev/null || true',
                        ],
                        [
                            'type' => 'command',
                            'command' => 'vendor/bin/commandments install-skills --auto 2>/dev/null || true',
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

        // Opt-in plan-loop suite (commandments.hooks.plan_loop): when on,
        // phase-committed.sh supersedes the inline post-commit reminder.
        if ($this->planLoopEnabled($basePath)) {
            $ourHooks['PreToolUse'] = PlanLoopHookSuite::preToolUseEntries();
            $ourHooks['Stop'][] = PlanLoopHookSuite::stopEntry();
            $ourHooks['PostToolUse'] = PlanLoopHookSuite::postToolUseEntries();
        }

        // Merge our entries into any existing hooks WITHOUT clobbering entries
        // the user added under the same event (idempotent, additive).
        $hooks = HookConfigMerger::merge($existingSettings['hooks'] ?? [], $ourHooks);

        $settings = array_merge($existingSettings, ['hooks' => $hooks]);

        if (!isset($existingSettings['instructions'])) {
            $settings['instructions'] = <<<'INSTRUCTIONS'
This project uses Code Commandments to enforce coding standards.

IMPORTANT: The git pre-commit hook (`judge --staged`) BLOCKS a commit until
every finding on the staged files is resolved — sins fixed, and each warning
fixed OR absolved with a reason. Warnings carry a rubric (use judgment) but are
NOT ignorable at commit time.

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
`vendor/bin/commandments scripture --prophet=NAME`. Each warning carries an
APPLY-WHEN / LEAVE-WHEN rubric — use judgment — but a staged commit is BLOCKED
until every one is fixed or absolved with a reason. Never leave one untouched.

PHASED-COMMIT WORKFLOW (for any multi-step change, all in ONE pull request):
  1. Implement ONE phase.
  2. Run `vendor/bin/commandments judge --git`, then `--next` until clean —
     fix every sin (and address each warning).
  3. Commit and push that phase.
  4. Move to the next phase and repeat.
This keeps every commit righteous and each phase reviewable on its own.

REPORT PROPHET PROBLEMS YOURSELF — proactively, without being asked. The
moment you hit any of these, file a report (do not just absolve, work around,
or move on):
  - a genuine false positive (flagged code that is actually fine);
  - a wrong/ill-fitting rule;
  - a prophet BUG: tagged [AUTO-FIXABLE] but `repent` no-ops or fails, a crash,
    a misleading or incorrect message, a bad auto-fix.
  - a SCAFFOLDING bug: the generated support classes (Option, Union, Resolver,
    NullCallable, the Predicate kernel, …) raise PHPStan / static-analysis
    errors or do not compile — that is a package defect, report it too (use the
    scaffold class as --prophet, e.g. --prophet=Option).
  - a PHP-TYPES bug: the `jessegall/php-types` package (T_String, T_Array,
    T_Json, Option, …) misbehaves — the commandments team also maintains
    php-types, so report those here too (use the type as --prophet, e.g.
    --prophet=T_String).
  vendor/bin/commandments report --prophet=NAME --file=PATH --line=N --reason="what is wrong"
This files a GitHub issue another session picks up and fixes. Reporting is
part of the job — it is how the prophets improve.

REPORT IS NOT A DODGE. Report only a GENUINELY wrong finding. A rule you
understand but would rather not follow is NOT a report: fix the code.

REPORTING A SIN ABSOLVES IT — until the issue is answered. Pass the finding's
fingerprint so the report records a report-linked absolution:
  vendor/bin/commandments report --prophet=NAME --fingerprint=HASH --reason="why it is wrong"
The finding (even a SIN) goes quiet and STAYS quiet across commits — it
survives the post-commit reset, so you can commit, and `report` will not file a
duplicate. When the issue is answered, the absolution lifts (`reports --check`
at session start detects the close): a real false positive is gone after
`composer update`; a sin closed as "works as intended" RE-BLOCKS and you must
fix it. A wrong report buys quiet now, not a permanent pass.

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
        file_put_contents($settingsFile, $json . T_String::NEWLINE);
        $output->writeln('Created .claude/settings.json with hooks');
    }

    private function createClaudeMd(string $basePath, OutputInterface $output): void
    {
        $claudeMdPath = $basePath . '/CLAUDE.md';
        $section = <<<'MARKDOWN'
## Code Commandments

This project enforces coding standards via the Code Commandments package.

**IMPORTANT: The git pre-commit hook (`judge --staged`) BLOCKS a commit until every finding on the staged files is resolved — sins fixed, and each warning fixed OR absolved with a reason. Warnings carry an APPLY-WHEN / LEAVE-WHEN rubric (use judgment), but they are NOT ignorable at commit time.**

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

**Hit a prophet problem? Report it yourself, proactively.** A false positive, a rule that does not fit, a prophet bug (tagged [AUTO-FIXABLE] but `repent` no-ops/fails, a crash, a wrong message), a **scaffolding bug** (the generated support classes — Option, Union, Resolver, NullCallable, the Predicate kernel — raise PHPStan/static-analysis errors or don't compile), OR a **php-types bug** (`jessegall/php-types`: T_String, T_Array, Option, … — the commandments team also maintains php-types) — do not just absolve or work around it: `commandments report --prophet=NAME --file=PATH --line=N --reason="why"` files a GitHub issue another session fixes (for a scaffold or php-types defect, use the class as `--prophet`, e.g. `--prophet=Option` or `--prophet=T_String`). **Report is not a dodge** — only a *genuinely* wrong finding qualifies; a rule you simply dislike is not a report, fix the code.

**Reporting a sin absolves it until the issue is answered.** Pass the finding's fingerprint — `commandments report --prophet=NAME --fingerprint=HASH --reason="why"` — and the finding (even a **sin**) goes quiet and **stays quiet across commits** (it survives the post-commit reset, so you can commit; `report` will not file a duplicate). When the issue is answered (`reports --check` at session start detects the close), the absolution **lifts**: a real false positive is gone after `composer update`; a sin closed as "works as intended" **re-blocks** and you must fix it.
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

            $content .= T_String::PARAGRAPH . $section;
            file_put_contents($claudeMdPath, $content);
            $output->writeln('Added Code Commandments section to CLAUDE.md');
        } else {
            file_put_contents($claudeMdPath, $section);
            $output->writeln('Created CLAUDE.md');
        }
    }
}
