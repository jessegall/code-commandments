<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Cli\Orchestration\Receipt;
use JesseGall\PhpTypes\Option;

/**
 * One MOMENT in a build, said in the build's own words — so a project ties into "an item was reported"
 * rather than reverse-engineering it from a tool call it happened to see. A harness hook (`PreToolUse`,
 * `Stop`, `PreCompact`) is a TRANSPORT a moment may arrive on; this is the moment itself, and a subclass
 * of this base is ALREADY TRUE by the time a handler sees it — {@see Vetoable} is the one that is not.
 */
abstract readonly class Event
{
    /**
     * @param  string  $root  the project this moment was raised in — carried rather than resolved from
     *                        the process's own cwd, because a handler asking where it is standing is the
     *                        way identity gets answered by a process that has wandered.
     * @param  Option<Receipt>  $receipt  what a tool READ for this item, absent when nothing measured it
     */
    public function __construct(
        public string $root,
        public Claim $claim,
        public Option $receipt,
    ) {}

    /**
     * The piece of work this moment is about.
     */
    public function item(): string
    {
        return $this->claim->item;
    }

    /**
     * Who holds it — the worker, never the process.
     */
    public function holder(): string
    {
        return $this->claim->hold->holder;
    }

    /**
     * How this moment reads where a handler has to name it back — the event, then the item.
     */
    public function label(): string
    {
        return static::class . ' on ' . $this->item();
    }
}
