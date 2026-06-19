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
        {--at= : Target a finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; combine with --prophet to disambiguate ties}
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

        $absolver = new Absolver($manager, $registry, $tracker);
        $fingerprint = $this->option('fingerprint');
        $at = $this->option('at');

        // Resolve --at=path:line[-to] to a fingerprint (the locator judge prints).
        if ((! is_string($fingerprint) || T_String::isBlank($fingerprint)) && is_string($at) && T_String::isNotBlank($at)) {
            $resolved = $this->resolveAt($absolver, $at);

            if ($resolved === null) {
                return self::FAILURE;
            }

            $fingerprint = $resolved;
        }

        if (! is_string($fingerprint) || T_String::isBlank($fingerprint)) {
            $this->error('Pass --fingerprint=<hash> or --at=path:line (copy either from judge --next).');

            return self::FAILURE;
        }

        $result = $absolver->absolve(trim($fingerprint), $this->option('reason'), $untilPush);

        if ($result['status'] === Absolver::STATUS_OK) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }

    /**
     * Resolve a `--at=path:line[-to]` locator to a single finding fingerprint,
     * printing an error (and returning null) when it is malformed, matches
     * nothing, or is ambiguous (narrow with --prophet).
     */
    private function resolveAt(Absolver $absolver, string $at): ?string
    {
        $loc = Absolver::parseLocator($at);

        if ($loc === null) {
            $this->error('--at must be path:line or path:from-to (e.g. --at=src/Foo.php:32).');

            return null;
        }

        $prophet = $this->option('prophet');
        $prophetFilter = is_string($prophet) && $prophet !== '' ? $prophet : null;

        $unique = [];

        foreach ($absolver->findingsAt($loc['path'], $loc['from'], $loc['to'], $prophetFilter) as $finding) {
            $unique[$finding->fingerprint] = $finding;
        }

        if ($unique === []) {
            $this->error("No live finding at {$at}" . ($prophetFilter !== null ? " for a prophet matching '{$prophetFilter}'" : '') . '. Run judge --next to see current findings.');

            return null;
        }

        if (count($unique) > 1) {
            $this->error("Multiple findings at {$at} — narrow with --prophet=NAME:");

            foreach ($unique as $finding) {
                $this->line("  - {$finding->prophetShort} ({$finding->location()})");
            }

            return null;
        }

        return array_values($unique)[0]->fingerprint;
    }
}
