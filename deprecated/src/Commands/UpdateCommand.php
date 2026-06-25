<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\CommandmentsUpdater;

/**
 * The one command to stay current — what Composer's post-update / post-install
 * scripts run. It wires those scripts into composer.json (self-installing, no
 * plugin) and then syncs: new prophets register, the .gitignore block + active
 * profile re-assert, scaffold and skills refresh.
 */
class UpdateCommand extends Command
{
    protected $signature = 'commandments:update';

    protected $description = 'Stay current: wire the composer lifecycle scripts, then sync prophets / scaffold / skills / hooks';

    public function handle(): int
    {
        return CommandmentsUpdater::run(
            base_path(),
            fn (): int => $this->call('commandments:sync', ['--after' => 'previous']),
            fn (string $line) => $this->line($line),
            fn (string $line) => $this->error($line),
        );
    }
}
