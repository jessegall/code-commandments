<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\AbsolveService;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

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
        return AbsolveService::run(
            $manager,
            $registry,
            $tracker,
            [
                'clear' => (bool) $this->option('clear'),
                'clear_until_push' => (bool) $this->option('clear-until-push'),
                'until_push' => (bool) $this->option('until-push'),
                'warnings' => (bool) $this->option('warnings'),
                'scope' => $this->option('scope'),
                'prophet' => $this->option('prophet'),
                'all' => (bool) $this->option('all'),
                'fingerprint' => $this->option('fingerprint'),
                'at' => $this->option('at'),
                'reason' => $this->option('reason'),
            ],
            base_path(),
            fn (string $line) => $this->info($line),
            fn (string $line) => $this->warn($line),
        ) === AbsolveService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
