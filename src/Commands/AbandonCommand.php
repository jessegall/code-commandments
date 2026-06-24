<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageIndexCache;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;

/**
 * Leave the pilgrimage early. The walk is forward-only and locks `judge` / bulk
 * `repent` while it runs, so if a prophet genuinely can't be resolved, the agent
 * needs a clean way out that does NOT fake completion. `abandon` discards the walk —
 * `judge`/`repent` return — but does NOT mark it complete, so the pre-push gate still
 * enforces sins. Run `commandments:pilgrimage` to start a fresh walk.
 */
class AbandonCommand extends Command
{
    protected $signature = 'commandments:abandon';

    protected $description = 'Leave the current pilgrimage early (judge/repent return; the push gate still enforces sins)';

    public function handle(): int
    {
        Environment::setBasePath(base_path());

        if (! PilgrimageState::isActive(base_path())) {
            $this->warn('No pilgrimage in progress.');

            return self::SUCCESS;
        }

        PilgrimageState::clear(base_path());
        PilgrimageIndexCache::clear(base_path());

        $this->info('Pilgrimage abandoned. `judge` and `repent` are available again — the push gate still enforces unresolved sins. Run `commandments:pilgrimage` to start a fresh walk.');

        return self::SUCCESS;
    }
}
