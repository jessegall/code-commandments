<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedCondition;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Says the next run out loud every so often. Two of the parts below are this announcer's own state and
 * one is the tick's, so the condition is a sentence about the announcer that the announcer never says:
 * it belongs on it, under a name.
 */
final class TickAnnouncer
{
    private int $said = 0;

    private string $last = 'nothing yet';

    public function __construct(private readonly bool $automatic, private readonly int $since) {}

    #[Sinful(UnnamedCondition::class)]
    public function onTicked(ShiftTick $tick, ?DepotRun $next): void
    {
        if ($this->automatic && $tick->at > $this->since && $next !== null) {
            $this->said++;
            $this->last = $next->reference;
        }
    }

    public function heard(): string
    {
        return match (true) {
            $this->said === 0 => 'silent',
            $this->said === 1 => 'said once: '.$this->last,
            default => sprintf('said %d times, last %s', $this->said, $this->last),
        };
    }
}
