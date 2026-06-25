<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;

/**
 * Advance the pilgrimage by one prophet. Forward-only: it re-scans ONLY the current
 * prophet to verify it is resolved, re-shows it while findings remain, and never
 * re-scans a prophet already passed. Run `commandments:pilgrimage` first.
 */
class NextCommand extends Command
{
    protected $signature = 'commandments:next {--scroll=backend}';

    protected $description = 'Advance the pilgrimage to the next prophet (forward-only, verify-before-advance)';

    public function handle(): int
    {
        Environment::setBasePath(base_path());

        $runner = new PilgrimageRunner(base_path(), config('commandments', []), (string) $this->option('scroll'));
        $step = $runner->advance();

        if ($step === null) {
            $this->line('No pilgrimage in progress. Run `php artisan commandments:pilgrimage` to begin.');

            return self::SUCCESS;
        }

        foreach (PilgrimagePresenter::render($step, $runner) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
