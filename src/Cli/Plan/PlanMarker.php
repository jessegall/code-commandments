<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
use JesseGall\CodeCommandments\Workspace;

/**
 * The per-session record that a plan is being executed — the state behind the keep-going Stop hook,
 * written on approval, read at every stop, cleared by `commandments plan done`. It is ONE
 * {@see StateFile} in the session's own {@see Workspace} folder, carrying the {@see PlanState}
 * counters and the stuck signal as named values: one session never nudges another, nothing
 * config-derived goes stale in it, and a signal cannot outlive the plan it belongs to.
 */
final class PlanMarker
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.plan-active'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'Active-plan marker for the code-commandments keep-going Stop hook (`commandments plan-reminder`). '
                . "Written when a plan is approved, read on every stop, cleared by `commandments plan done` or\n"
                . 'when the branch merges back.',
            [
                'head' => 'the git HEAD at the last keep-going nudge',
                'no_progress_nudges' => 'nudges since HEAD last moved — a spinning agent is capped by it, and '
                    . 'progress resets it',
                'total_nudges' => 'every keep-going nudge this plan has had',
                'stuck' => 'yes = the agent is BLOCKED and needs the human. One-shot: it suppresses the '
                    . 'next stop nudge and clears itself, and the plan stays ACTIVE (it is NOT done)',
                'stuck_at' => 'the HEAD it was marked stuck at, for reference (`commandments plan status`)',
            ],
            defaults: new State(head: '', no_progress_nudges: 0, total_nudges: 0, stuck: false, stuck_at: ''),
            safe: 'deleting it simply ends the keep-going nudges for this plan',
        );
    }

    /**
     * Record that a plan is now active at $head, with the nudge counters reset. A fresh plan starts
     * from the EMPTY state, so nothing of the plan before it — least of all a stuck signal — carries in.
     */
    public function activate(string $head): void
    {
        $this->file->write(new State(
            head: $head,
            no_progress_nudges: 0,
            total_nudges: 0,
            stuck: false,
            stuck_at: '',
        ));
    }

    public function isActive(): bool
    {
        return $this->file->exists();
    }

    /**
     * Signal that the plan is STUCK — the agent is blocked and needs the human, but the plan is NOT
     * done. The keep-going Stop hook suppresses the NEXT stop (so a blocked agent isn't looped back in)
     * and clears this immediately, yet the plan stays active — so the moment the agent continues, normal
     * nudging resumes. One-shot: it never lingers to disable keep-going for the rest of the run. $head is
     * recorded for reference (what `plan status` shows). Distinct from `plan done`, which ENDS the plan.
     */
    public function markStuck(string $head): void
    {
        $this->file->write($this->file->read()->with(stuck: true, stuck_at: $head));
    }

    /**
     * The HEAD the plan was marked stuck at, or null when it isn't stuck. An empty string means it was
     * marked stuck with no commits yet.
     */
    public function stuckAt(): ?string
    {
        $state = $this->file->read();

        return $state->flag('stuck') ? trim($state->text('stuck_at')) : null;
    }

    public function clearStuck(): void
    {
        $this->file->write($this->file->read()->with(stuck: false, stuck_at: ''));
    }

    /**
     * Count one keep-going nudge at $currentHead and return the fresh {@see PlanState}.
     */
    public function recordNudge(string $currentHead): PlanState
    {
        $state = $this->state()->nudged($currentHead);
        $this->save($state);

        return $state;
    }

    /**
     * The plan is over — and the stuck signal goes with it, since it is part of the same state.
     */
    public function clear(): void
    {
        $this->file->delete();
    }

    /**
     * The persisted {@see PlanState} — the empty state when there is no marker, since absence is
     * modelled as the empty state rather than patched per-field.
     */
    private function state(): PlanState
    {
        $state = $this->file->read();

        return new PlanState($state->text('head'), $state->int('no_progress_nudges'), $state->int('total_nudges'));
    }

    /**
     * Write the nudge counters, keeping whatever stuck signal stands — a nudge is not what resolves it.
     */
    private function save(PlanState $plan): void
    {
        $this->file->write($this->file->read()->with(
            head: $plan->head,
            no_progress_nudges: $plan->stuck,
            total_nudges: $plan->total,
        ));
    }
}
