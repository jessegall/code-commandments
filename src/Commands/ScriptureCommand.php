<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\ProphetRegistry;

/**
 * Reveal the holy commandments.
 *
 * Display all registered prophets and their sacred commandments.
 */
class ScriptureCommand extends Command
{
    protected $signature = 'commandments:scripture
        {--scroll= : Filter by specific scroll (group)}
        {--detailed : Show full scripture (detailed descriptions)}
        {--claude : Output optimized for Claude Code AI assistant}';

    protected $description = 'Reveal the holy commandments and their prophets';

    public function handle(ProphetRegistry $registry): int
    {
        $scrollFilter = $this->option('scroll');
        $detailed = $this->option('detailed');
        $claudeMode = (bool) $this->option('claude');

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        if ($claudeMode) {
            return $this->showClaudeOutput($registry, $scrolls, $detailed);
        }

        return $this->showStandardOutput($registry, $scrolls, $detailed);
    }

    /**
     * @param array<string> $scrolls
     */
    private function showStandardOutput(ProphetRegistry $registry, array $scrolls, bool $detailed): int
    {
        $this->output->writeln('<fg=yellow>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║              THE SACRED SCRIPTURE                         ║');
        $this->output->writeln('  ║         Behold the commandments and their prophets        ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->newLine();

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                $this->error("Unknown scroll: {$scroll}");
                continue;
            }

            $this->output->writeln("  <fg=cyan>📜 THE SCROLL OF " . strtoupper($scroll) . "</>");
            $this->output->writeln("  " . str_repeat('═', 55));
            $this->newLine();

            $prophets = $registry->getProphets($scroll);
            $number = 1;

            foreach ($prophets as $prophet) {
                $className = class_basename($prophet);
                $canRepent = $prophet instanceof SinRepenter;
                $requiresConfession = $prophet->requiresConfession();

                $badges = [];
                if ($canRepent) {
                    $badges[] = '<fg=green>⚡ auto-fix</>';
                }
                if ($requiresConfession) {
                    $badges[] = '<fg=yellow>👁 review</>';
                }

                $badgeStr = !empty($badges) ? ' ' . implode(' ', $badges) : '';

                $this->output->writeln("  <fg=white>Commandment #{$number}:</> <fg=magenta>{$className}</>{$badgeStr}");
                $this->output->writeln("  <fg=gray>{$prophet->description()}</>");

                if ($detailed) {
                    $this->newLine();
                    $detailedDesc = $prophet->detailedDescription();
                    $lines = explode("\n", $detailedDesc);
                    foreach ($lines as $line) {
                        $this->output->writeln("    <fg=gray>{$line}</>");
                    }
                }

                $this->newLine();
                $number++;
            }
        }

        $totalCount = $registry->totalCount();
        $this->output->writeln("  <fg=gray>Total commandments: {$totalCount}</>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param array<string> $scrolls
     */
    private function showClaudeOutput(ProphetRegistry $registry, array $scrolls, bool $detailed): int
    {
        $this->output->writeln('CODE COMMANDMENTS');
        $this->output->writeln('=================');
        $this->output->newLine();
        $this->output->writeln('These are the coding standards enforced by this project.');
        $this->output->writeln('Violations are called "sins" and must be fixed before committing.');
        $this->output->newLine();

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $this->output->writeln(strtoupper($scroll) . ' RULES:');
            $this->output->writeln(str_repeat('-', 40));

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                $className = str_replace('Prophet', '', class_basename($prophet));
                $canRepent = $prophet instanceof SinRepenter;
                $requiresConfession = $prophet->requiresConfession();

                $badges = [];
                if ($canRepent) {
                    $badges[] = 'auto-fixable';
                }
                if ($requiresConfession) {
                    $badges[] = 'requires-review';
                }

                $badgeStr = !empty($badges) ? ' [' . implode(', ', $badges) . ']' : '';

                $this->output->writeln("- {$className}{$badgeStr}");
                $this->output->writeln("  {$prophet->description()}");

                if ($detailed) {
                    $this->output->newLine();
                    $detailedDesc = $prophet->detailedDescription();
                    $lines = explode("\n", $detailedDesc);
                    foreach ($lines as $line) {
                        $this->output->writeln("  {$line}");
                    }
                }

                $this->output->newLine();
            }
        }

        $totalCount = $registry->totalCount();
        $this->output->writeln("Total rules: {$totalCount}");
        $this->output->newLine();
        $this->output->writeln('Run `php artisan commandments:judge --claude` to check for violations.');

        return self::SUCCESS;
    }
}
