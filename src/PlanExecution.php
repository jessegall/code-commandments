<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The project's plan-execution profile — how a plan branches, commits, and checks itself as an
 * agent grinds it phase by phase. Configured inside {@see Config::planExecution}; it is the config
 * surface behind the `commandments checks` / `commandments plan` commands and the `executing-plans`
 * skill. Every setter returns `$this`, so it composes as a block or a single fluent arrow:
 *
 *   $config->planExecution(fn (PlanExecution $plan) => $plan
 *       ->branchPrefix('plan/')
 *       ->pushEachPhase()
 *       ->keepGoing()
 *       ->onStart('composer install')
 *       ->eachPhase('composer lint')
 *       ->onComplete('composer test'));
 *
 * The three check moments ({@see Moment}) are deliberately distinct: {@see onStart} is one-time
 * setup, {@see eachPhase} is the cheap between-phase signal (a full suite here would drown the
 * grind), and {@see onComplete} is the exhaustive end gate. `judge --branch` is never listed —
 * the `complete` gate always appends it, so a plan can never finish unjudged.
 */
final class PlanExecution
{
    /** @var list<string> */
    private array $onStart = [];

    /** @var list<string> */
    private array $eachPhase = [];

    /** @var list<string> */
    private array $onComplete = [];

    private string $baseBranch = 'main';

    private string $branchPrefix = 'plan/';

    private bool $pushEachPhase = false;

    private ?StopPolicy $stopPolicy = null;

    /**
     * The branch a plan is cut from and judged against — the base for the new plan branch and the
     * `judge --branch=<base>` the end gate runs. Defaults to `main`.
     */
    public function branchFrom(string $base): self
    {
        $this->baseBranch = $base;

        return $this;
    }

    /**
     * The prefix for the branch a plan auto-creates (`plan/` → `plan/<slug>`). Defaults to `plan/`.
     */
    public function branchPrefix(string $prefix): self
    {
        $this->branchPrefix = $prefix;

        return $this;
    }

    /**
     * Push after every phase commit, rather than once at the end. Off by default — a plan pushes
     * once when it's done, so the branch doesn't churn mid-flight.
     */
    public function pushEachPhase(bool $push = true): self
    {
        $this->pushEachPhase = $push;

        return $this;
    }

    /**
     * Turn on the keep-going Stop hook: while a plan is active, a stop re-nudges the agent to carry
     * on until the plan is done, per the {@see StopPolicy}. Opt-in — without this call the Stop
     * hook stays silent.
     */
    public function keepGoing(StopPolicy $policy = StopPolicy::UntilComplete): self
    {
        $this->stopPolicy = $policy;

        return $this;
    }

    /**
     * Commands to run ONCE before the first phase — environment setup the whole plan needs
     * (`composer install`, `npm ci`, a `git fetch`). Not a place for tests; those belong on the
     * phases and the end gate.
     */
    public function onStart(string ...$commands): self
    {
        $this->onStart = [...$this->onStart, ...$commands];

        return $this;
    }

    /**
     * Commands to run after EACH phase's commit — the fast, cheap signal (a linter, a type check)
     * that keeps a phase honest without the full suite. Keep it quick: it runs once per phase. The
     * phase's own scoped tests are chosen by the agent, not listed here.
     */
    public function eachPhase(string ...$commands): self
    {
        $this->eachPhase = [...$this->eachPhase, ...$commands];

        return $this;
    }

    /**
     * Commands to run ONCE at the very end, after the last phase — the exhaustive gate: the full
     * test suite, a lint, a static analysis. `judge --branch` is appended after these
     * automatically, so it always runs last; you never list it yourself.
     */
    public function onComplete(string ...$commands): self
    {
        $this->onComplete = [...$this->onComplete, ...$commands];

        return $this;
    }

    /**
     * The commands declared for one {@see Moment} — the single accessor the `checks` command
     * reads, so a new moment is one enum case + one bucket, not a new getter everywhere.
     *
     * @return list<string>
     */
    public function checksFor(Moment $moment): array
    {
        return match ($moment) {
            Moment::Start => $this->onStart,
            Moment::Phase => $this->eachPhase,
            Moment::Complete => $this->onComplete,
        };
    }

    public function baseBranch(): string
    {
        return $this->baseBranch;
    }

    public function prefix(): string
    {
        return $this->branchPrefix;
    }

    public function pushesEachPhase(): bool
    {
        return $this->pushEachPhase;
    }

    /**
     * The configured keep-going policy, or null when the Stop hook is off (the default).
     */
    public function stopPolicy(): ?StopPolicy
    {
        return $this->stopPolicy;
    }
}
