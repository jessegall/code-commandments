<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\RepentService;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Auto-fix ONLY the prophet the pilgrimage is currently on ([AUTO-FIXABLE] findings),
 * in place. Does NOT advance — call `commandments:next` yourself after.
 */
class AutofixCommand extends Command
{
    protected $signature = 'commandments:autofix {--scroll=backend} {--dry-run}';

    protected $description = 'Auto-fix the CURRENT pilgrimage prophet ([AUTO-FIXABLE] findings only), in place';

    public function handle(ProphetRegistry $registry, ScrollManager $manager): int
    {
        Environment::setBasePath(base_path());

        $state = PilgrimageState::load(base_path());

        if ($state === null || $state->complete) {
            $this->line('No pilgrimage in progress. Run `php artisan commandments:pilgrimage`, walk to an [AUTO-FIXABLE] prophet, then autofix.');

            return self::SUCCESS;
        }

        $runner = new PilgrimageRunner(base_path(), config('commandments', []), (string) $this->option('scroll'));
        $peek = $runner->peek();
        $prophet = $peek['prophet'] ?? null;

        if ($prophet === null) {
            $this->line('No current prophet to auto-fix.');

            return self::SUCCESS;
        }

        if (($peek['auto_fixable'] ?? false) === false) {
            $count = count($peek['locations'] ?? []);
            $this->line("{$prophet} is NOT [AUTO-FIXABLE] — its {$count} finding(s) must be resolved by hand. Run `commandments todo` to list them, fix each, then `commandments next`.");

            return self::SUCCESS;
        }

        $this->line("Auto-fixing the current prophet: {$prophet}");

        $service = new RepentService($manager, $registry, $this->output->writeln(...));

        return $service->run([
            'prophet' => $prophet,
            'files' => $state->scope,
            'dry_run' => (bool) $this->option('dry-run'),
        ]) === RepentService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
