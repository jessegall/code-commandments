<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\Binary;

/**
 * Everything a dispatched agent is told, in ONE place — who it is, what to do, what it is doing it to, and
 * how to reach that rather than only its name. Complete on purpose, since anything an agent resolves for
 * itself it resolves correctly and about the wrong thing; and short, because it is written for a SUBAGENT
 * of the orchestrator, whose board, plan and journal are already the parent's.
 */
final readonly class Envelope
{
    public function __construct(
        private Profile $profile,
        private Duty $duty,
        private string $root,
        private Reminders $reminders,
    ) {}

    /**
     * $source is REQUIRED, not defaulted. Every moment knows where its subject can be seen, and a blank
     * standing in for "there was no source" is the same blank as "nobody passed one" — which is how a
     * brief comes to say nothing about the thing it is about.
     */
    public function opening(string $subject, string $source): string
    {
        $holes = Holes::none()
            ->with('agent', $this->duty->agent)
            ->with('procedure', $this->duty->procedure)
            ->with('subject', $subject)
            ->with('role', $this->profile->role($this->duty->agent)->unwrapOr(''))
            ->with('work', $this->profile->procedure($this->duty->procedure)->unwrapOr(''))
            ->with('source', $source === '' ? '(nothing further was recorded about it)' : $source)
            ->with('binary', Binary::in($this->root));

        // Through Reminders like every other piece of prose, so the profile-then-shipped rule is stated
        // once. Keeping a copy here 'as a fallback' is what Reminders' own docblock forbids: a second
        // copy is one that drifts, and the one that drifts is the one nobody reads.
        return $this->reminders->insist('dispatch', $holes);
    }
}
