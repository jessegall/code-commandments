<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\ProphetRegistry;

/**
 * Reveal the commandments.
 */
class ScriptureCommand extends Command
{
    protected $signature = 'commandments:scripture
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Show details for a specific prophet}
        {--detailed : Show full descriptions with examples}';

    protected $description = 'List all commandments and their descriptions';

    public function handle(ProphetRegistry $registry): int
    {
        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');
        $detailed = $this->option('detailed') || $prophetFilter;

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        // If filtering by prophet, find and show just that one
        if ($prophetFilter) {
            return $this->showProphetDetails($registry, $prophetFilter);
        }

        $this->output->writeln('CODE COMMANDMENTS');
        $this->output->newLine();
        $this->output->writeln('IMPORTANT: Never commit code with sins. Fix all violations first.');
        $this->output->newLine();

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $this->output->writeln(strtoupper($scroll) . ':');

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                $className = str_replace('Prophet', '', class_basename($prophet));
                $canRepent = $prophet instanceof SinRepenter;

                $badge = $canRepent ? ' [AUTO-FIXABLE]' : '';

                $this->output->writeln("- {$className}{$badge}: {$prophet->description()}");

                if ($detailed) {
                    $detailedDesc = $prophet->detailedDescription();
                    $lines = explode("\n", $detailedDesc);
                    foreach ($lines as $line) {
                        $this->output->writeln("  {$line}");
                    }
                    $this->output->newLine();
                }
            }

            $this->output->newLine();
        }

        $this->output->writeln('Check violations: php artisan commandments:judge');
        $this->output->writeln('Auto-fix sins: php artisan commandments:repent');

        return self::SUCCESS;
    }

    /**
     * Show detailed information for a specific prophet.
     */
    private function showProphetDetails(ProphetRegistry $registry, string $prophetFilter): int
    {
        $found = $registry->findProphet($prophetFilter);

        if (!$found) {
            $this->output->writeln("Prophet '{$prophetFilter}' not found.");
            return self::FAILURE;
        }

        $prophet = $found['prophet'];
        $className = class_basename($prophet);
        $shortName = str_replace('Prophet', '', $className);
        $canRepent = $prophet instanceof SinRepenter;

        $this->output->writeln(strtoupper($shortName));
        $this->output->newLine();
        $this->output->writeln($prophet->description());
        if ($canRepent) {
            $this->output->writeln('[AUTO-FIXABLE with: php artisan commandments:repent]');
        }
        $this->output->newLine();

        $detailedDesc = $prophet->detailedDescription();
        $this->output->writeln($detailedDesc);

        return self::SUCCESS;
    }
}
