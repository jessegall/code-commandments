<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageIndexCache;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;

/**
 * Begin the forward-only doctrine walk, pillar by pillar, one prophet at a time.
 * Resets any prior pilgrimage and stops at the first prophet with findings;
 * `commandments:next` advances it (verify-before-advance).
 */
class PilgrimageCommand extends Command
{
    protected $signature = 'commandments:pilgrimage {--scroll=backend}
        {--is-complete : INTERNAL: exit 0 only if THIS session has genuinely walked the whole pilgrimage (the pre-push gate uses this). Recomputed from the cursor}
        {--clear : INTERNAL: discard the pilgrimage state (the pre-push gate consumes a completed walk so the next push re-arms the gate)}';

    protected $description = 'Begin the forward-only doctrine walk (resets state; commandments:next advances it)';

    public function handle(): int
    {
        Environment::setBasePath(base_path());

        if ((bool) $this->option('clear')) {
            PilgrimageState::clear(base_path());
            PilgrimageIndexCache::clear(base_path());

            return self::SUCCESS;
        }

        $runner = new PilgrimageRunner(base_path(), config('commandments', []), (string) $this->option('scroll'));

        if ((bool) $this->option('is-complete')) {
            return $runner->isComplete() ? self::SUCCESS : self::FAILURE;
        }

        $step = $runner->begin();

        $this->line(sprintf('The pilgrimage begins. %d doctrines ahead.', $runner->totalDoctrines()));

        foreach (PilgrimagePresenter::render($step, $runner) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
