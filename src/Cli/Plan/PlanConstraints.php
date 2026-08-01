<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\Line;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\PlanProfile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The constraint state for the active plan — the natural-language invariants the run must hold to (the
 * global ones from {@see PlanProfile::constraints} plus this run's local additions), and whether the
 * agent has verified them against its branch diff. Verification is a HEAD stamp, fresh only while HEAD
 * is unchanged. Session-scoped like {@see PlanMarker}, and written in the shared {@see StateFile}
 * format: the stamp is a named value, the constraints are the list beneath it, and one file holds
 * both — a stamp that outlived the constraints it vouched for was only ever a way to go wrong.
 */
final class PlanConstraints
{
    public function __construct(
        private readonly StateFile $file,
        private readonly PlanProfile $plan,
    ) {}

    public static function inSession(Workspace $workspace, PlanProfile $plan): self
    {
        return new self(new StateFile($workspace->path('.plan-constraints'), self::legend()), $plan);
    }

    public static function legend(): Legend
    {
        return new Legend(
            "The invariants THIS plan run must hold to, on top of the project's own "
                . '(`$config->planExecution(...)->constraints(...)`), and whether the agent has checked its '
                . 'branch diff against them.',
            ['verified_at' => 'the git HEAD the constraints were last verified at. A moved HEAD is stale — '
                . 'there is new work to re-check'],
            defaults: new State(verified_at: ''),
            list: 'one constraint per line, added for this run with `commandments plan constrain "<rule>"`.',
            safe: 'deleting it drops this run\'s extra constraints and asks for the check again',
        );
    }

    /**
     * Every constraint in force for this run — the global ones first, then this run's local additions.
     *
     * @return list<string>
     */
    public function active(): array
    {
        return [...$this->plan->constraints(), ...$this->local()];
    }

    /**
     * The constraints added for THIS run only.
     *
     * @return list<string>
     */
    public function local(): array
    {
        return $this->file->read()->items();
    }

    /**
     * Record a constraint for this run — idempotent, so re-adding the same rule is a no-op.
     */
    public function addLocal(string $rule): void
    {
        $rule = Line::flatten($rule);
        $existing = $this->local();

        if ($rule === '' || in_array($rule, $existing, true)) {
            return;
        }

        $this->file->write($this->file->read()->withItems([...$existing, $rule]));
    }

    /**
     * Stamp that the constraints were verified at $head.
     */
    public function markVerified(string $head): void
    {
        $this->file->write($this->file->read()->with(verified_at: $head));
    }

    /**
     * Were the constraints verified at exactly $head — the current HEAD, with no commit since? A moved
     * HEAD is stale (new work to re-check); an empty $head (no commits yet) is never fresh.
     */
    public function isVerifiedAt(string $head): bool
    {
        return $head !== '' && $this->file->read()->text('verified_at') === $head;
    }

    /**
     * Drop this run's local constraints AND its verification stamp — the plan is finished.
     */
    public function clear(): void
    {
        $this->file->delete();
    }
}
