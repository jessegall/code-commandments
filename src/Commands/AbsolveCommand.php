<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Absolve a single finding by fingerprint, with a required reason.
 */
class AbsolveCommand extends Command
{
    protected $signature = 'commandments:absolve
        {--fingerprint= : The finding fingerprint shown by judge --next}
        {--reason= : Why the rule does not apply here (required)}';

    protected $description = 'Absolve a single finding by fingerprint, with a required reason';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        $fingerprint = $this->option('fingerprint');

        if (! is_string($fingerprint) || trim($fingerprint) === '') {
            $this->error('--fingerprint is required (copy it from judge --next).');

            return self::FAILURE;
        }

        $result = (new Absolver($manager, $registry, $tracker))
            ->absolve(trim($fingerprint), $this->option('reason'));

        if ($result['status'] === Absolver::STATUS_OK) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
