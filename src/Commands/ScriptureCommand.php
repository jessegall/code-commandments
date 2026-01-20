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
        {--detailed : Show full scripture (detailed descriptions)}';

    protected $description = 'Reveal the holy commandments and their prophets';

    public function handle(ProphetRegistry $registry): int
    {
        $this->output->writeln('<fg=yellow>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║              THE SACRED SCRIPTURE                         ║');
        $this->output->writeln('  ║         Behold the commandments and their prophets        ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->newLine();

        $scrollFilter = $this->option('scroll');
        $detailed = $this->option('detailed');

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

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
}
