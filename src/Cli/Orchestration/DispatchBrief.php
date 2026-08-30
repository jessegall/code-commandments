<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Workspace;

/**
 * What a dispatched agent is told — the PROSE half of a dispatch, and only that: reading the bindings,
 * queueing, claiming the board and spawning belong to the funnel that dispatches. ONE place, because a
 * brief has a part that is the trigger's to say and a part that is nobody's to leave out, and the second
 * only stays true while no caller has the chance to forget it.
 */
final readonly class DispatchBrief
{
    /**
     * The brief that opens a dispatched agent's conversation.
     */
    private const string OPENING = 'dispatch-opening';

    /**
     * The brief handed to one already running, when the next subject is its turn.
     */
    private const string CONTINUED = 'dispatch-continued';

    /**
     * The duty every brief carries, whichever of the two it is: ANNOUNCE. An orchestrator's whole problem
     * is that work finishes and nothing tells it, so every agent it starts says when it arrives and what
     * it did before it leaves. In the trigger, a second trigger had to remember to repeat it; in the
     * PROCEDURE, a project rewording its own could drop it without meaning to. It is a fact about being
     * dispatched rather than about what you were dispatched to do.
     */
    private const string ANNOUNCE = 'dispatch-announce';

    public function __construct(
        private Reminders $reminders,
        private string $binary,
    ) {}

    public static function inSession(Workspace $workspace, string $root): self
    {
        return new self(Reminders::inSession($workspace), Binary::in($root));
    }

    /**
     * The whole of what an agent starting on $subject is told. $session is the ORCHESTRATOR's own id —
     * read at fire time, since it is the one thing a dispatched agent cannot look up for itself.
     */
    public function opening(Profile $profile, Duty $duty, string $subject, string $session): string
    {
        $holes = Holes::none()
            ->with('agent', $duty->agent)
            ->with('role', $profile->role($duty->agent)->unwrapOr(''))
            ->with('procedure', $profile->procedure($duty->procedure)->unwrapOr(''))
            ->with('subject', $subject)
            ->with('session', $session);

        return $this->and($this->reminders->insist(self::OPENING, $holes), $duty);
    }

    /**
     * What an agent ALREADY reading is handed when the next subject comes up. It is not told how to find
     * the orchestrator again — it was told once, and the id has not moved.
     */
    public function continuing(Duty $duty, string $subject): string
    {
        $holes = Holes::none()
            ->with('agent', $duty->agent)
            ->with('subject', $subject);

        return $this->and($this->reminders->insist(self::CONTINUED, $holes), $duty);
    }

    /**
     * $brief with the announcing duty after it.
     */
    private function and(string $brief, Duty $duty): string
    {
        $holes = Holes::none()
            ->with('item', $duty->procedure)
            ->with('binary', $this->binary);

        return rtrim($brief) . "\n\n" . $this->reminders->insist(self::ANNOUNCE, $holes);
    }
}
