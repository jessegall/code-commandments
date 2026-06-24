<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;

/**
 * Begin the forward-only doctrine walk, pillar by pillar, one prophet at a time.
 * Resets any prior pilgrimage and stops at the first prophet with findings;
 * `commandments:next` advances it (verify-before-advance).
 */
class PilgrimageCommand extends Command
{
    protected $signature = 'commandments:pilgrimage {--scroll=backend}';

    protected $description = 'Begin the forward-only doctrine walk (resets state; commandments:next advances it)';

    public function handle(): int
    {
        Environment::setBasePath(base_path());

        $runner = new PilgrimageRunner(base_path(), config('commandments', []), (string) $this->option('scroll'));
        $step = $runner->begin();

        $this->line(sprintf('The pilgrimage begins. %d doctrines ahead.', $runner->totalDoctrines()));

        foreach (PilgrimagePresenter::render($step, $runner) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
