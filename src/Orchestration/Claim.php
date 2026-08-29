<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * One item, held by one worker, from when it was claimed until somebody releases it. The hold is a fact
 * about the BOARD, never about a process: a worker's process ends every time it reports, and a claim that
 * died with it would free the item in the very window the orchestrator is deciding what to hand out next
 * — reintroducing the collision the claim exists to prevent.
 */
final readonly class Claim
{
    public function __construct(
        public string $item,
        public Hold $hold,
        public Stage $stage,
        public int $round = 1,
    ) {}

    public function at(Stage $stage): self
    {
        return new self($this->item, $this->hold, $stage, $this->round);
    }

    /**
     * The same claim, sent back for another round — the round is what lets a reader see three attempts on
     * one item, which usually means the item was mis-specified rather than the worker failing.
     */
    public function reworked(): self
    {
        return new self($this->item, $this->hold, Stage::Working, $this->round + 1);
    }

    public function isHeldBy(string $holder): bool
    {
        return $this->hold->isBy($holder);
    }

    public function toLine(): string
    {
        return implode("\t", [$this->item, $this->hold->holder, $this->stage->value, $this->hold->since, (string) $this->round]);
    }

    /**
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode("\t", $line, 5);

        if (count($fields) !== 5) {
            return Option::none();
        }

        [$item, $holder, $stage, $since, $round] = $fields;

        return Option::fromNullable(Stage::tryFrom($stage))
            ->map(fn (Stage $at) => new self($item, new Hold($holder, $since), $at, (int) $round));
    }

    /**
     * How it reads on the board — the stage, who holds it, since when, and which round.
     */
    public function render(): string
    {
        $round = $this->round > 1 ? "  round {$this->round}" : '';

        return sprintf(
            '  %-10s %-14s %-22s %s%s',
            $this->stage->value,
            $this->hold->holder,
            $this->item,
            $this->hold->since,
            $round,
        );
    }
}
