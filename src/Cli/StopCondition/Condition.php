<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

use JesseGall\CodeCommandments\Cli\State\Line;
use JesseGall\PhpTypes\Option;

/**
 * One thing the user said must hold before the agent may stop — its STABLE id, its text, and the
 * reason it cannot move without the user. Being blocked is a fact about a CONDITION, so the reason
 * is recorded here ({@see StopConditionGate::markBlocked}) rather than as activity beside the gate.
 */
final readonly class Condition
{
    /**
     * @param  Option<string>  $blockedBecause
     */
    public function __construct(
        public int $id,
        public string $text,
        public Option $blockedBecause,
    ) {}

    /**
     * A condition as the user has just stated it — nothing is blocking it yet.
     */
    public static function stated(int $id, string $text): self
    {
        return new self($id, $text, Option::none());
    }

    /**
     * The condition as the file stores it: `id<TAB>text`, and the reason as a third column once there
     * is one.
     */
    public function line(): string
    {
        return implode("\t", [$this->id, $this->text, ...$this->blockedBecause]);
    }

    /**
     * Read a stored line back — null when it carries no id and text at all.
     */
    public static function read(string $line): ?self
    {
        $parts = explode("\t", $line, 3);

        if (count($parts) < 2) {
            return null;
        }

        [$id, $text, $reason] = array_pad($parts, 3, '');

        return new self((int) $id, $text, Option::fromTruthy($reason));
    }

    public function isBlocked(): bool
    {
        return $this->blockedBecause->isSome();
    }

    /**
     * The same condition, waiting on the user for $reason. A blank reason is no claim at all, so it
     * leaves the condition standing.
     */
    public function blockedBy(string $reason): self
    {
        return $this->because(Option::fromTruthy(Line::flatten($reason)));
    }

    /**
     * The same condition with any block dropped — what a held stop does to every condition, so a claim
     * is always made afresh about the list as it stands NOW.
     */
    public function unblocked(): self
    {
        return $this->because(Option::none());
    }

    /**
     * The same condition — same id, same words — carrying $reason instead of whatever it carried.
     * The one place the statement itself is re-spelled, so a claim about it is a line rather than a
     * rebuild.
     *
     * @param  Option<string>  $reason
     */
    private function because(Option $reason): self
    {
        return new self($this->id, $this->text, $reason);
    }
}
