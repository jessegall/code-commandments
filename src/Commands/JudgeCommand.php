<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\JudgeService;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Judge the codebase for sins. Thin adapter over {@see JudgeService}.
 */
class JudgeCommand extends Command
{
    protected $signature = 'commandments:judge
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Summon a specific prophet by name}
        {--file= : Judge a specific file}
        {--files= : Judge specific files (comma-separated)}
        {--path= : Override the scroll path and target a specific directory (bypasses all excludes)}
        {--git : Only judge files that are new or changed in git}
        {--staged : Only judge files staged for commit (what the pre-commit gate uses)}
        {--branch : Judge everything changed since the branch base, including committed work (survives intermediate commits — the grind reckoning)}
        {--no-profile : Ignore the active profile for this run: scan the whole scroll and show warnings, regardless of the profile (audit the full codebase)}
        {--absolve : Mark files as absolved after confession (manual review)}
        {--no-cache : Force a fresh judge — never read the findings cache (the pre-commit gate uses this to stay authoritative)}
        {--no-parallel : Judge sequentially (no forked workers)}
        {--next : Show exactly one finding at a time (fix or absolve to advance)}
        {--plan : Print the remediation roadmap: every finding ordered root-cause-first as a numbered checklist (the penance plan)}';

    protected $description = 'Judge the codebase for sins against the commandments';

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        $service = new JudgeService(
            $manager,
            $registry,
            $tracker,
            'php artisan commandments',
            ':',
            fn (string $line) => $this->output->writeln($line),
            fn (string $line) => $this->error($line),
        );

        return $service->run([
            'scroll' => $this->option('scroll'),
            'prophet' => $this->option('prophet'),
            'file' => $this->option('file'),
            'files' => $this->option('files')
                ? Pipeline::from(explode(',', $this->option('files')))->map(fn ($f) => trim($f))->toArray()
                : [],
            'path' => $this->option('path'),
            'git' => (bool) $this->option('git'),
            'staged' => (bool) $this->option('staged'),
            'branch' => (bool) $this->option('branch'),
            'no_profile' => (bool) $this->option('no-profile'),
            'absolve' => (bool) $this->option('absolve'),
            'no_cache' => (bool) $this->option('no-cache'),
            'next' => (bool) $this->option('next'),
            'no_parallel' => (bool) $this->option('no-parallel'),
            'plan' => (bool) $this->option('plan'),
        ]) === JudgeService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
