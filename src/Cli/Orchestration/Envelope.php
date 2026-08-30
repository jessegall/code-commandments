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

        foreach ($this->profile->reminder('dispatch', $holes) as $written) {
            return $written;
        }

        return $holes->fill($this->shipped());
    }

    /**
     * The envelope when a profile has not written its own. It is stated here so a project that never took
     * the template still gets an agent that knows its whole job from the words it was given.
     */
    private function shipped(): string
    {
        return <<<'TEXT'
            You are `{agent}`. This is WHO you are:

            {role}

            This is WHAT to do, against {subject}:

            {work}

            Where to find {subject} itself: {source}

            You are a subagent of the orchestrator that dispatched you, so its board, its plan and its
            journal are the ones you read — `{binary} build` answers for the build you are part of, and
            `{procedure}`, which you hold on it, is something you can actually see. Report by RETURNING:
            a SHORT account of what you did and what you found, in your final message. Do not go looking
            for the orchestrator; it is the thing that called you.
            TEXT;
    }
}
