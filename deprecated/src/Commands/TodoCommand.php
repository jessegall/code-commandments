<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;

/**
 * Compact "what's still to fix here" for the CURRENT pilgrimage prophet — just the
 * remaining file:line locations, no scripture or banners. Does NOT advance the walk.
 */
class TodoCommand extends Command
{
    protected $signature = 'commandments:todo {--scroll=backend}';

    protected $description = 'List the still-unresolved file:line locations for the current pilgrimage prophet (compact; does not advance)';

    public function handle(): int
    {
        Environment::setBasePath(base_path());

        $runner = new PilgrimageRunner(base_path(), config('commandments', []), (string) $this->option('scroll'));
        $step = $runner->peek();

        if ($step === null) {
            $this->line('No pilgrimage in progress. Run `php artisan commandments:pilgrimage` to begin.');

            return self::SUCCESS;
        }

        if (($step['complete'] ?? false) === true) {
            $this->line('✓ The pilgrimage is complete — nothing left to fix.');

            return self::SUCCESS;
        }

        $prophet = $step['prophet'] ?? '?';
        $locations = $step['locations'] ?? [];

        if ($locations === []) {
            $this->line("✓ {$prophet} is clean — run `php artisan commandments:next` to advance.");

            return self::SUCCESS;
        }

        $this->line(sprintf('%s — %d still to resolve:', $prophet, count($locations)));

        foreach ($locations as $location) {
            $tag = ($location['autoFixable'] ?? false) === true ? ' [AUTO-FIXABLE]' : '';
            $this->line(sprintf('  %s:%s%s', $location['file'], $location['line'] ?? '?', $tag));
        }

        return self::SUCCESS;
    }
}
