<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * A moment a profile can bind an agent to, and WHAT IT CARRIES. The second half is the contract: `commit`
 * happens to carry a sha because it was written first and put one there by hand, and `worker-finished`
 * carried nothing at all — which is not a bug in `worker-finished`, it is the absence of this type. An
 * agent dispatched on a moment with no subject is asked to work on nothing, and four of them were.
 */
enum Moment: string
{
    case Commit = 'commit';

    case WorkerFinished = 'worker-finished';

    /**
     * The moment named $name, absent when the package raises no such moment — which is what a profile
     * binding a typo looks like, and it should be said rather than registered and left inert.
     *
     * @return Option<self>
     */
    public static function named(string $name): Option
    {
        return Option::fromNullable(self::tryFrom($name));
    }

    /**
     * What this moment's SUBJECT is — the thing the agent is dispatched to work on. Stated here so a
     * trigger cannot decide it privately and a profile need not read our source to find out.
     */
    public function carries(): string
    {
        return match ($this) {
            self::Commit => 'the sha of the commit that landed on this checkout',
            self::WorkerFinished => 'the worker that stopped — its type where it has one, else its id',
        };
    }

    /**
     * The harness event that raises it. A binding over an unwired event is a healthy-looking line above a
     * dead transport, which is exactly what printed correctly all evening while nothing ran.
     */
    public function raisedBy(): string
    {
        return match ($this) {
            self::Commit => 'PostToolUse (a `git commit` that moved this checkout\'s HEAD)',
            self::WorkerFinished => 'SubagentStop',
        };
    }

    /**
     * A subject to rehearse with — what `orchestrate test` dispatches instead of waiting for the real
     * moment. It is deliberately recognisable as a rehearsal: an agent that receives one should be able
     * to say so rather than going looking for a commit nobody made.
     */
    public function rehearsal(): string
    {
        return 'REHEARSAL-' . $this->value;
    }
}
