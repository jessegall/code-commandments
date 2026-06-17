<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\PhpTypes\T_String;

/**
 * Absolve a single finding by fingerprint, with a required reason.
 */
class AbsolveCommand extends Command
{
    protected $signature = 'commandments:absolve
        {--fingerprint= : The finding fingerprint shown by judge --next}
        {--reason= : Why the rule does not apply here (required)}
        {--all : Baseline the queue: absolve every current advisory finding at once (sins still block)}
        {--clear : Remove every absolution (used by the post-commit reset so nothing stays hidden)}';

    protected $description = 'Absolve a single finding by fingerprint, with a required reason';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        if ((bool) $this->option('clear')) {
            $cleared = $tracker->clearFindingAbsolutions();
            $this->info("Cleared {$cleared} absolution(s). Every finding will be re-evaluated from scratch.");

            return self::SUCCESS;
        }

        if ((bool) $this->option('all')) {
            $result = (new Absolver($manager, $registry, $tracker))->absolveAll($this->option('reason'));

            $this->info("Baselined the queue: absolved {$result['absolved']} advisory finding(s).");

            if ($result['blocking_sins'] > 0) {
                $this->warn("{$result['blocking_sins']} sin(s) cannot be absolved and still block — fix them with: php artisan commandments:judge --next");
            }

            return self::SUCCESS;
        }

        $fingerprint = $this->option('fingerprint');

        if (! is_string($fingerprint) || T_String::isBlank($fingerprint)) {
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
