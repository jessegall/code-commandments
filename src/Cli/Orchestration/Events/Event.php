<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * One MOMENT in a build, said in the build's own words, so a project ties into "a worker finished"
 * rather than reverse-engineering it from a tool call it happened to see — a harness hook is a TRANSPORT
 * a moment may arrive on, where this is the moment itself. The base carries only what EVERY moment has:
 * an item and a holder belong to {@see ItemEvent}, since a worker can stop having touched the board
 * never.
 */
abstract readonly class Event
{
    /**
     * @param  string  $root  the project this moment was raised in — carried rather than resolved from
     *                        the process's own cwd, because a handler asking where it is standing is the
     *                        way identity gets answered by a process that has wandered.
     */
    public function __construct(public string $root) {}

    /**
     * How this moment reads where a handler has to name it back.
     */
    public function label(): string
    {
        return static::class;
    }
}
