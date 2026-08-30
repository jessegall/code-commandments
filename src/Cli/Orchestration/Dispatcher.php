<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Workspace;

/**
 * The ONE place a dispatch is composed — which agents a moment is bound to, whether the same work is
 * already waiting, and what the agent will be told. It STARTS nothing: a hook has no channel to the
 * terminal of the person whose machine an agent would appear on, and a process it detached outlives the
 * binding that asked for it, so the work is written down and the orchestrator's own stop is held until it
 * makes the call itself — in its own session, where a subagent shares the board, the plan and the journal.
 */
final readonly class Dispatcher
{
    public function __construct(
        private Workspace $workspace,
        private string $root,
    ) {}

    /**
     * Record whatever $trigger is bound to, against $subject, and say what happened. $source is what is
     * known about the subject beyond its name — where the orchestrator goes to see it for itself. The
     * lines come back rather than being printed: the caller is a hook, and only it knows whether this
     * moment is one a user should see.
     *
     * @return list<string>
     */
    public function fire(string $trigger, string $subject, string $source): array
    {
        $said = [];

        foreach (Profiles::inForce($this->workspace) as $profile) {
            foreach ($profile->boundTo($trigger) as $duty) {
                foreach ($this->record($profile, $duty, $trigger, $subject, $source) as $line) {
                    $said[] = $line;
                }
            }
        }

        return $said;
    }

    /**
     * @return list<string>
     */
    private function record(Profile $profile, Duty $duty, string $trigger, string $subject, string $source): array
    {
        if ($profile->procedure($duty->procedure)->isNone()) {
            return ["Code Commandments — `{$profile->name}` binds `{$duty->procedure}` on {$trigger} but has not written it."];
        }

        // REFUSED, never recorded. A moment arriving without a subject is one nobody can be told to act
        // on: the brief then reads "this is WHAT to do, against :", and an agent handed that either
        // invents its job or reconstructs it from disk. One loud error beats four confused agents.
        if ($subject === '') {
            return ["Code Commandments — nothing was recorded for `{$duty->agent}` on {$trigger}: the moment carried no subject. A moment is a name AND what it carries; this one carried nothing, so there was nothing to hand over."];
        }

        $work = new Dispatched(gmdate('Y-m-d H:i:s'), $trigger, $subject, $duty->agent, $duty->procedure, $source);

        if (! Pending::inSession($this->workspace)->add($work)) {
            return [sprintf(
                'Code Commandments — `%s` is already waiting to run `%s` against %s; it is not owed twice.',
                $duty->agent,
                $duty->procedure,
                $this->short($subject),
            )];
        }

        return [sprintf(
            'Code Commandments — `%s` on %s: `%s` is to run `%s` against %s. It is written down, and your '
                . 'next stop is HELD until you have dispatched it yourself with the Agent tool — a hook '
                . 'cannot start one where anybody can see it, and one it started behind you would outlive '
                . 'the binding that asked for it.',
            $trigger,
            $this->short($subject),
            $duty->agent,
            $duty->procedure,
            $this->short($subject),
        )];
    }

    /**
     * The brief for one piece of waiting work — what the orchestrator pastes into the Agent call it is
     * about to make. Built here and by the stop that holds for it, from the same class, so what is
     * promised and what is handed over cannot drift apart.
     */
    public function briefFor(Dispatched $work): string
    {
        foreach (Profiles::inForce($this->workspace) as $profile) {
            return new Envelope($profile, new Duty($work->agent, $work->procedure), $this->root)
                ->opening($work->subject, $work->source);
        }

        return "Carry out `{$work->procedure}` against {$work->subject}, and report what you found.";
    }

    /**
     * A subject short enough to read in a line. A sha shortens; anything else is already a name.
     */
    private function short(string $subject): string
    {
        return strlen($subject) === 40 ? substr($subject, 0, 7) : $subject;
    }
}
