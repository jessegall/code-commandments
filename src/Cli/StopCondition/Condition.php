<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

use JesseGall\CodeCommandments\Cli\State\Line;
use JesseGall\PhpTypes\Option;

/**
 * One thing the user said must hold before the agent may stop — its STABLE id, its text, the reason it
 * cannot move without them, and whether it is PARKED. Being blocked is a fact about a condition, so the
 * reason lives here rather than beside the gate; parking is the same shape one level up, because a gate
 * holding every stop on a project's whole backlog cannot be satisfied by doing the work, and a gate like
 * that is one an agent learns to route around.
 */
final readonly class Condition
{
    /**
     * How a parked condition marks itself in the file — a word rather than a flag, so a human reading the
     * file sees what it means.
     */
    private const string PARKED = 'parked';

    public function __construct(
        public Statement $statement,
        public Standing $standing,
    ) {}

    /**
     * A condition as the user has just stated it — nothing is blocking it yet.
     */
    public static function stated(int $id, string $text): self
    {
        return new self(new Statement($id, $text), Standing::holding());
    }

    /**
     * The condition as the file stores it: `id<TAB>text`, and the reason as a third column once there
     * is one.
     */
    public function line(): string
    {
        $reason = $this->reason();

        return $this->standing->isHolding()
            ? rtrim(implode("\t", [$this->statement->id, $this->statement->text, $reason]), "\t")
            : implode("\t", [$this->statement->id, $this->statement->text, $reason, self::PARKED]);
    }

    /**
     * Read a stored line back — null when it carries no id and text at all.
     */
    public static function read(string $line): ?self
    {
        $parts = explode("\t", $line, 4);

        if (count($parts) < 2) {
            return null;
        }

        [$id, $text, $reason, $parked] = array_pad($parts, 4, '');

        $standing = match (true) {
            $parked === self::PARKED => Standing::parked(),
            $reason !== '' => Standing::blockedBecause($reason),
            default => Standing::holding(),
        };

        return new self(new Statement((int) $id, $text), $standing);
    }

    /**
     * Take this out of the hold, keeping it and its reason in the record. What a finding worth writing
     * down but not worth stopping for becomes.
     */
    public function parked(): self
    {
        return $this->standingAs(Standing::parked());
    }

    /**
     * Put it back in the hold — the user pulling a parked item into this session's work.
     */
    public function unparked(): self
    {
        return $this->standingAs(Standing::holding());
    }

    /**
     * Is this condition holding a stop? Only what this session promised is.
     */
    public function isHolding(): bool
    {
        return $this->standing->isHolding();
    }

    /**
     * Why it cannot move without the user, or nothing where it can.
     */
    public function reason(): string
    {
        return $this->standing->blockedBecause->unwrapOr('');
    }

    public function isBlocked(): bool
    {
        return $this->standing->isBlocked();
    }

    /**
     * The same condition, waiting on the user for $reason. A blank reason is no claim at all, so it
     * leaves the condition standing.
     */
    public function blockedBy(string $reason): self
    {
        $reason = Line::flatten($reason);

        return $this->standingAs($reason === '' ? Standing::holding() : Standing::blockedBecause($reason));
    }

    /**
     * The same condition with any block dropped — what a held stop does to every condition, so a claim
     * is always made afresh about the list as it stands NOW.
     */
    public function unblocked(): self
    {
        return $this->standing->isHolding() ? $this->standingAs(Standing::holding()) : $this;
    }

    /**
     * The same condition — same id, same words — standing differently. The one place the statement is
     * re-spelled, so a claim about it is a line rather than a rebuild.
     */
    private function standingAs(Standing $standing): self
    {
        return new self($this->statement, $standing);
    }
}
