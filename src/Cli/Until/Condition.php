<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Until;

use JesseGall\CodeCommandments\Cli\State\Line;

/**
 * One thing the user said must hold before the agent may stop — its STABLE id, its text, and, once
 * the agent has said so, the reason it cannot move without the user.
 *
 * The reason lives HERE, on the condition, and not in a counter beside the gate. An agent works the
 * list in whatever order it likes, so "how much has happened since the last hold" could never answer
 * the question `stuck` actually asks — which of THESE is blocked, and why. Being blocked is a fact
 * about a condition, so it is recorded against that condition, one at a time
 * ({@see UntilGate::markBlocked}), and `stuck` is simply the moment every standing condition carries
 * one.
 */
final readonly class Condition
{
    public function __construct(
        public int $id,
        public string $text,
        public string $blockedBecause = '',
    ) {}

    /**
     * The condition as the file stores it: `id<TAB>text`, and the reason as a third column once there
     * is one.
     */
    public function line(): string
    {
        return $this->blockedBecause === ''
            ? "{$this->id}\t{$this->text}"
            : "{$this->id}\t{$this->text}\t{$this->blockedBecause}";
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

        return new self((int) $parts[0], $parts[1], $parts[2] ?? '');
    }

    public function isBlocked(): bool
    {
        return $this->blockedBecause !== '';
    }

    /**
     * The same condition, waiting on the user for $reason. A blank reason is no claim at all, so it
     * leaves the condition standing.
     */
    public function blockedBy(string $reason): self
    {
        return new self($this->id, $this->text, Line::flatten($reason));
    }

    /**
     * The same condition with any block dropped — what a held stop does to every condition, so a claim
     * is always made afresh about the list as it stands NOW.
     */
    public function unblocked(): self
    {
        return new self($this->id, $this->text);
    }
}
