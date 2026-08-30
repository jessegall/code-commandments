<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Cli\Orchestration\Receipt;
use JesseGall\PhpTypes\Option;

/**
 * A moment about a piece of WORK — it has an item, a holder, and whatever a tool read for it. Separate
 * from {@see Event} because not every moment has those: a worker can stop having touched the board
 * never, and a base that demanded a claim would have forced one to be invented.
 */
abstract readonly class ItemEvent extends Event
{
    /**
     * @param  Option<Receipt>  $receipt  what a tool READ for this item, absent when nothing measured it
     */
    public function __construct(
        string $root,
        public Claim $claim,
        public Option $receipt,
    ) {
        parent::__construct($root);
    }

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

    public function label(): string
    {
        return static::class . ' on ' . $this->item();
    }
}
