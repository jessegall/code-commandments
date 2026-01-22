<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Judge the codebase for sins.
 *
 * The prophets will examine your code and reveal transgressions
 * against the sacred commandments.
 */
class JudgeCommand extends Command
{
    protected $signature = 'commandments:judge
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Summon a specific prophet by name}
        {--file= : Judge a specific file}
        {--files= : Judge specific files (comma-separated)}
        {--git : Only judge files that are new or changed in git}
        {--absolve : Mark files as absolved after confession (manual review)}
        {--summary : Show summary output only (for hooks)}';

    protected $description = 'Judge the codebase for sins against the sacred commandments';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');
        $fileFilter = $this->option('file');
        $filesFilter = $this->option('files')
            ? array_map('trim', explode(',', $this->option('files')))
            : [];
        $gitMode = $this->option('git');
        $shouldAbsolve = $this->option('absolve');
        $summaryMode = $this->option('summary');

        $gitFiles = $gitMode ? $this->getGitChangedFiles() : [];

        if ($gitMode && empty($gitFiles)) {
            if (!$summaryMode) {
                $this->info('No new or changed files found in git.');
            }

            return self::SUCCESS;
        }

        if (!$summaryMode) {
            $this->output->writeln('<fg=yellow>');
            $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
            $this->output->writeln('  ║          THE PROPHETS HAVE BEEN SUMMONED                  ║');
            $this->output->writeln('  ║       Let thy code be judged by the commandments          ║');
            $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
            $this->output->writeln('</>');
            $this->newLine();
        }

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        $totalSins = 0;
        $totalWarnings = 0;
        $totalFiles = 0;
        $manualVerificationFiles = [];
        $violatedProphets = [];

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                if (!$summaryMode) {
                    $this->error("Unknown scroll: {$scroll}");
                }
                continue;
            }

            if (!$summaryMode) {
                $this->info("📜 Examining the scroll of <fg=cyan>{$scroll}</>");
                $this->newLine();
            }

            if ($fileFilter) {
                $results = $manager->judgeFile($scroll, $fileFilter);
                $results = collect([$fileFilter => $results]);
            } elseif (!empty($filesFilter)) {
                $results = $manager->judgeFiles($scroll, $filesFilter);
            } elseif ($gitMode) {
                if (empty($gitFiles)) {
                    continue;
                }
                $results = $manager->judgeFiles($scroll, $gitFiles);
            } else {
                $results = $manager->judgeScroll($scroll);
            }

            foreach ($results as $filePath => $judgments) {
                $relativePath = str_replace(base_path().'/', '', $filePath);
                $fileSins = 0;
                $fileWarnings = 0;

                foreach ($judgments as $prophetClass => $judgment) {
                    // Apply prophet filter if specified
                    if ($prophetFilter) {
                        $shortName = class_basename($prophetClass);
                        if (!str_contains(strtolower($shortName), strtolower($prophetFilter))) {
                            continue;
                        }
                    }

                    // Check if file is absolved
                    if ($tracker->isAbsolved($filePath, $prophetClass)) {
                        $content = file_get_contents($filePath);
                        if ($content !== false && !$tracker->hasChangedSinceAbsolution($filePath, $prophetClass, $content)) {
                            continue; // Skip absolved and unchanged files
                        }
                    }

                    $prophet = app($prophetClass);

                    foreach ($judgment->sins as $sin) {
                        $fileSins++;
                        $violatedProphets[$prophetClass] = true;

                        if (!$summaryMode) {
                            $line = $sin->line ? ":{$sin->line}" : '';
                            $this->output->writeln("  <fg=red>✗</> <fg=white>{$relativePath}{$line}</>");
                            $this->output->writeln("    <fg=red>{$sin->message}</>");
                            if ($sin->suggestion) {
                                $this->output->writeln("    <fg=gray>→ {$sin->suggestion}</>");
                            }
                        }
                    }

                    foreach ($judgment->warnings as $warning) {
                        $fileWarnings++;
                        $violatedProphets[$prophetClass] = true;

                        // Track files requiring manual verification
                        if ($prophet->requiresConfession()) {
                            $manualVerificationFiles[$relativePath][] = [
                                'prophet' => class_basename($prophetClass),
                                'message' => $warning->message,
                                'line' => $warning->line,
                            ];
                        }

                        if (!$summaryMode) {
                            $line = $warning->line ? ":{$warning->line}" : '';
                            $this->output->writeln("  <fg=yellow>⚠</> <fg=white>{$relativePath}{$line}</>");
                            $this->output->writeln("    <fg=yellow>{$warning->message}</>");
                        }
                    }

                    // Handle absolution for warnings
                    if ($shouldAbsolve && $judgment->hasWarnings()) {
                        $content = file_get_contents($filePath);
                        if ($content !== false) {
                            $tracker->absolve($filePath, $prophetClass, 'Reviewed via commandments:judge --absolve');
                            if (!$summaryMode) {
                                $this->output->writeln("    <fg=green>✓ Absolved</>");
                            }
                        }
                    }
                }

                $totalSins += $fileSins;
                $totalWarnings += $fileWarnings;
                if ($fileSins > 0 || $fileWarnings > 0) {
                    $totalFiles++;
                }
            }

            if (!$summaryMode) {
                $summary = $manager->getSummary($results);
                $this->newLine();
                $this->output->writeln("  <fg=gray>Files examined: {$summary['files']}, Righteous: {$summary['righteous']}, Fallen: {$summary['fallen']}</>");
                $this->newLine();
            }
        }

        // Show manual verification section
        if (!empty($manualVerificationFiles)) {
            $this->newLine();
            $this->output->writeln('<fg=magenta>');
            $this->output->writeln('  ┌───────────────────────────────────────────────────────────┐');
            $this->output->writeln('  │         📋 FILES REQUIRING MANUAL VERIFICATION            │');
            $this->output->writeln('  └───────────────────────────────────────────────────────────┘');
            $this->output->writeln('</>');
            $this->newLine();

            foreach ($manualVerificationFiles as $file => $issues) {
                $this->output->writeln("  <fg=white>{$file}</>");
                foreach ($issues as $issue) {
                    $line = $issue['line'] ? ":{$issue['line']}" : '';
                    $this->output->writeln("    <fg=magenta>→ [{$issue['prophet']}]{$line}</> {$issue['message']}");
                }
                $this->newLine();
            }

            $this->output->writeln('  <fg=gray>Review these files manually and run with --absolve to mark as reviewed.</>');
            $this->newLine();
        }

        // Show violated prophets with detailed descriptions
        if (!empty($violatedProphets) && !$summaryMode) {
            $this->showViolatedProphetDetails(array_keys($violatedProphets));
        }

        $this->newLine();
        if ($totalSins === 0 && $totalWarnings === 0) {
            $this->output->writeln('<fg=green>');
            $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
            $this->output->writeln('  ║                   THY CODE IS RIGHTEOUS                   ║');
            $this->output->writeln('  ║            The prophets find no transgressions            ║');
            $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
            $this->output->writeln('</>');

            return self::SUCCESS;
        }

        if ($summaryMode) {
            // Compact output for hooks
            if ($totalSins > 0) {
                $this->output->writeln("<fg=red>⛔ {$totalSins} sins found in {$totalFiles} files. Run 'php artisan commandments:judge' for details.</>");
            }
            if (count($manualVerificationFiles) > 0) {
                $this->output->writeln('<fg=magenta>📋 '.count($manualVerificationFiles)." files need manual verification.</>");
            }
        } else {
            $this->output->writeln('<fg=red>');
            $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
            $this->output->writeln("  ║  {$totalSins} SINS found across {$totalFiles} files".str_repeat(' ', max(0, 35 - strlen((string) $totalSins) - strlen((string) $totalFiles))).'║');
            if ($totalWarnings > 0) {
                $this->output->writeln("  ║  {$totalWarnings} WARNINGS requiring review".str_repeat(' ', max(0, 34 - strlen((string) $totalWarnings))).'║');
            }
            $this->output->writeln('  ║                                                             ║');
            $this->output->writeln('  ║      Run "php artisan commandments:repent" for absolution   ║');
            $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
            $this->output->writeln('</>');
        }

        return $totalSins > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Show detailed descriptions for violated prophets.
     *
     * @param  array<string>  $prophetClasses
     */
    private function showViolatedProphetDetails(array $prophetClasses): void
    {
        $this->newLine();
        $this->output->writeln('<fg=cyan>');
        $this->output->writeln('  ┌───────────────────────────────────────────────────────────┐');
        $this->output->writeln('  │              📖 COMMANDMENT DETAILS                       │');
        $this->output->writeln('  └───────────────────────────────────────────────────────────┘');
        $this->output->writeln('</>');

        foreach ($prophetClasses as $prophetClass) {
            $prophet = app($prophetClass);
            $shortName = class_basename($prophetClass);

            $this->newLine();
            $this->output->writeln("  <fg=cyan;options=bold>{$shortName}</>");
            $this->output->writeln("  <fg=white>{$prophet->description()}</>");
            $this->newLine();

            // Show detailed description with proper indentation
            $detailed = $prophet->detailedDescription();
            $lines = explode("\n", $detailed);
            foreach ($lines as $line) {
                $this->output->writeln("  <fg=gray>{$line}</>");
            }
        }
    }

    /**
     * Get files that are new or changed in git.
     *
     * @return array<string>
     */
    private function getGitChangedFiles(): array
    {
        $basePath = base_path();
        $files = [];

        // Get modified and staged files (tracked files with changes)
        $diffOutput = shell_exec('git diff --name-only HEAD 2>/dev/null');
        if ($diffOutput) {
            foreach (explode("\n", trim($diffOutput)) as $file) {
                if ($file !== '') {
                    $files[] = $basePath.'/'.$file;
                }
            }
        }

        // Get staged files (in case they're not in the diff against HEAD)
        $stagedOutput = shell_exec('git diff --name-only --cached 2>/dev/null');
        if ($stagedOutput) {
            foreach (explode("\n", trim($stagedOutput)) as $file) {
                if ($file !== '') {
                    $files[] = $basePath.'/'.$file;
                }
            }
        }

        // Get untracked files (new files not yet added to git)
        $untrackedOutput = shell_exec('git ls-files --others --exclude-standard 2>/dev/null');
        if ($untrackedOutput) {
            foreach (explode("\n", trim($untrackedOutput)) as $file) {
                if ($file !== '') {
                    $files[] = $basePath.'/'.$file;
                }
            }
        }

        return array_unique($files);
    }
}
