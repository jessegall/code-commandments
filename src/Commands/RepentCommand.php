<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Seek absolution through auto-fixing sins.
 *
 * The prophets who can offer absolution will transform
 * your code to follow the commandments.
 */
class RepentCommand extends Command
{
    protected $signature = 'commandments:repent
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Use a specific prophet for repentance}
        {--file= : Repent sins in a specific file}
        {--files= : Repent sins in specific files (comma-separated)}
        {--dry-run : Show what sins may be absolved without acting}';

    protected $description = 'Seek absolution through auto-fixing transgressions';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager
    ): int {
        $this->output->writeln('<fg=yellow>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║              SEEKING ABSOLUTION                           ║');
        $this->output->writeln('  ║      The prophets shall transform thy transgressions      ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->newLine();

        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');
        $fileFilter = $this->option('file');
        $filesFilter = $this->option('files')
            ? array_map('trim', explode(',', $this->option('files')))
            : [];
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        $totalAbsolved = 0;
        $totalFailed = 0;

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                $this->error("Unknown scroll: {$scroll}");
                continue;
            }

            $this->info("📜 Seeking absolution in the scroll of <fg=cyan>{$scroll}</>");
            $this->newLine();

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                // Only process prophets that can repent
                if (!$prophet instanceof SinRepenter) {
                    continue;
                }

                // Apply prophet filter if specified
                if ($prophetFilter) {
                    $shortName = class_basename($prophet);
                    if (!str_contains(strtolower($shortName), strtolower($prophetFilter))) {
                        continue;
                    }
                }

                $prophetName = class_basename($prophet);
                $this->output->writeln("  <fg=cyan>Prophet {$prophetName}</> offers absolution...");

                if ($fileFilter) {
                    $files = [new \SplFileInfo($fileFilter)];
                } elseif (!empty($filesFilter)) {
                    $files = array_map(fn ($f) => new \SplFileInfo($f), $filesFilter);
                } else {
                    $files = $manager->getFilesForScroll($scroll);
                }

                foreach ($files as $file) {
                    $filePath = $file->getRealPath();

                    if ($filePath === false) {
                        continue;
                    }

                    if (!$prophet->canRepent($filePath)) {
                        continue;
                    }

                    $content = file_get_contents($filePath);
                    if ($content === false) {
                        continue;
                    }

                    // First judge to see if there are sins
                    $judgment = $prophet->judge($filePath, $content);

                    if ($judgment->isRighteous()) {
                        continue;
                    }

                    // Attempt repentance
                    $result = $prophet->repent($filePath, $content);
                    $relativePath = str_replace(base_path() . '/', '', $filePath);

                    if ($result->absolved && $result->newContent !== null) {
                        if ($dryRun) {
                            $this->output->writeln("    <fg=yellow>Would absolve:</> {$relativePath}");
                            foreach ($result->penance as $action) {
                                $this->output->writeln("      <fg=gray>→ {$action}</>");
                            }
                            $totalAbsolved++;
                        } else {
                            // Create backup
                            $backupPath = $filePath . '.bak';
                            file_put_contents($backupPath, $content);

                            // Write new content
                            file_put_contents($filePath, $result->newContent);

                            $this->output->writeln("    <fg=green>✓ Absolved:</> {$relativePath}");
                            foreach ($result->penance as $action) {
                                $this->output->writeln("      <fg=gray>→ {$action}</>");
                            }
                            $totalAbsolved++;

                            // Remove backup if successful
                            unlink($backupPath);
                        }
                    } elseif (!$result->absolved) {
                        $this->output->writeln("    <fg=red>✗ Cannot absolve:</> {$relativePath}");
                        if ($result->failureReason) {
                            $this->output->writeln("      <fg=gray>→ {$result->failureReason}</>");
                        }
                        $totalFailed++;
                    }
                }
            }

            $this->newLine();
        }

        $this->newLine();
        if ($totalAbsolved === 0 && $totalFailed === 0) {
            $this->output->writeln('<fg=green>');
            $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
            $this->output->writeln('  ║           NO TRANSGRESSIONS REQUIRE ABSOLUTION            ║');
            $this->output->writeln('  ║                  Thy code is already pure                 ║');
            $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
            $this->output->writeln('</>');
            return self::SUCCESS;
        }

        $action = $dryRun ? 'would be' : 'have been';

        $this->output->writeln('<fg=green>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln("  ║  {$totalAbsolved} transgressions {$action} forgiven" . str_repeat(' ', max(0, 28 - strlen((string)$totalAbsolved) - strlen($action))) . '║');
        if ($totalFailed > 0) {
            $this->output->writeln("  ║  {$totalFailed} could not be absolved (manual fix required)" . str_repeat(' ', max(0, 17 - strlen((string)$totalFailed))) . '║');
        }
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');

        return self::SUCCESS;
    }
}
