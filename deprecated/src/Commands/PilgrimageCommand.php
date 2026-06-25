<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageIndexCache;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageStarter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;

/**
 * Begin the forward-only doctrine walk, pillar by pillar, one prophet at a time.
 * Resets any prior pilgrimage and stops at the first prophet with findings;
 * `commandments:next` advances it (verify-before-advance).
 */
class PilgrimageCommand extends Command
{
    /** Top-level verbs an agent may mistakenly pass as `pilgrimage <verb>` (they are their own commands). */
    private const TOP_LEVEL_COMMANDS = ['next', 'todo', 'autofix', 'abandon', 'absolve', 'report', 'judge', 'repent', 'skills', 'scripture'];

    protected $signature = 'commandments:pilgrimage {prophet? : Constrain the walk to ONE prophet (partial name, like judge --prophet)} {--scroll=backend}
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

        $prophet = $this->argument('prophet');

        if (is_string($prophet) && in_array(strtolower($prophet), self::TOP_LEVEL_COMMANDS, true)) {
            $this->warn("`{$prophet}` is a top-level command, not a pilgrimage argument. Did you mean `commandments {$prophet}`? (the pilgrimage's optional argument is a PROPHET name filter.)");

            return self::SUCCESS;
        }

        $step = PilgrimageStarter::start($runner, base_path(), is_string($prophet) ? $prophet : null, $this->line(...));

        if ($step === null) {
            return self::SUCCESS;
        }

        $this->line(sprintf('The pilgrimage begins. %d station(s) ahead.', $runner->totalDoctrines()));

        foreach (PilgrimagePresenter::render($step, $runner) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
