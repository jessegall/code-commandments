<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The fluent BUILDER for a project's plan-execution profile — how a plan branches, commits, and checks
 * itself as an agent grinds it phase by phase. The `$config->planExecution(...)` closure receives one and
 * chains setters (each returns `$this`); {@see build} freezes it into the read-only {@see PlanProfile} the
 * package consumes. Setters only — a read lives on the profile, so no chain can misfire on a getter.
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

    /** @var list<string> */
    private array $constraints = [];

    private bool $enforceEachPhase = false;

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
     * A CONSTRAINT the agent must respect for every plan run — a natural-language architectural
     * invariant `judge` can't decide (e.g. "the frontend is presentation-only"). The agent verifies it
     * by reviewing its own branch diff, and the `complete` gate blocks `plan done` until it does.
     */
    public function constraint(string ...$rules): self
    {
        $this->constraints = [...$this->constraints, ...$rules];

        return $this;
    }

    /**
     * Force the constraint diff-check after EVERY phase, not just at completion. Off by default — a
     * phase only gets a soft reminder, while completion is always the hard gate.
     */
    public function enforceConstraintsEachPhase(bool $enforce = true): self
    {
        $this->enforceEachPhase = $enforce;

        return $this;
    }

    /**
     * Freeze the configured state into the read-only {@see PlanProfile} the package consumes.
     */
    public function build(): PlanProfile
    {
        return new PlanProfile(
            $this->onStart,
            $this->eachPhase,
            $this->onComplete,
            $this->baseBranch,
            $this->branchPrefix,
            $this->pushEachPhase,
            $this->stopPolicy,
            $this->constraints,
            $this->enforceEachPhase,
        );
    }
}
