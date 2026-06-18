<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\GitFileDetector;
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
        {--warnings : Batch-absolve every WARNING in scope under one --reason; hard-refuses if any sin is in scope (absolves nothing)}
        {--scope= : Limit --warnings to changed files: "git" (vs tracked state) or "staged" (the index)}
        {--prophet= : Limit --warnings to one prophet (partial name match), e.g. --prophet=DuplicateCode — one scan, not one-per-finding}
        {--until-push : Make the absolution STICKY: it survives the post-commit reset and stays until git push (warnings only)}
        {--clear-until-push : Drop every push-scoped (until-push) absolution; used by the pre-push hook}
        {--clear : Remove every ordinary absolution (post-commit reset so nothing stays hidden); report-linked absolutions persist until their issue is answered}';

    protected $description = 'Absolve a single finding by fingerprint, with a required reason';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        if ((bool) $this->option('clear-until-push')) {
            $cleared = $tracker->clearUntilPushAbsolutions();
            $this->info("Cleared {$cleared} push-scoped absolution(s).");

            return self::SUCCESS;
        }

        if ((bool) $this->option('clear')) {
            $cleared = $tracker->clearFindingAbsolutions();
            $this->info("Cleared {$cleared} absolution(s). Every finding will be re-evaluated from scratch.");

            return self::SUCCESS;
        }

        $untilPush = (bool) $this->option('until-push');

        if ((bool) $this->option('warnings')) {
            $scope = $this->option('scope');
            $scopeFiles = match ($scope) {
                null => null,
                'git' => GitFileDetector::for(base_path())->getChangedFiles(),
                'staged' => GitFileDetector::for(base_path())->getStagedFiles(),
                default => false,
            };

            if ($scopeFiles === false) {
                $this->error('--scope must be "git" or "staged".');

                return self::FAILURE;
            }

            $prophet = $this->option('prophet');
            $result = (new Absolver($manager, $registry, $tracker))
                ->absolveWarnings($this->option('reason'), $scopeFiles, $untilPush, is_string($prophet) ? $prophet : null);

            $result['status'] === Absolver::STATUS_OK ? $this->info($result['message']) : $this->error($result['message']);

            return $result['status'] === Absolver::STATUS_OK ? self::SUCCESS : self::FAILURE;
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
            ->absolve(trim($fingerprint), $this->option('reason'), $untilPush);

        if ($result['status'] === Absolver::STATUS_OK) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
