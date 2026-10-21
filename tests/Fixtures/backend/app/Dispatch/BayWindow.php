<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedCondition;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The hours a bay is worked. Both conditions here are the righteous twins of a concept with no name:
 * one is a range check, three parts about ONE subject, which is a single idea already; the other is a
 * guard standing at the top of its body, which says what it means by leaving.
 */
final class BayWindow
{
    private int $loaded = 0;

    public function __construct(private readonly int $from, private readonly int $until, private readonly bool $open) {}

    #[Righteous(UnnamedCondition::class)]
    public function covers(int $at): bool
    {
        if ($at >= $this->from && $at <= $this->until && $this->open) {
            return true;
        }

        return false;
    }

    #[Righteous(UnnamedCondition::class)]
    public function load(Crate $crate, LoadingBay $bay): void
    {
        if (! $crate->isPacked() || ! $bay->isFree() || ! $this->open) {
            return;
        }

        $bay->take($crate);
        $this->loaded++;
    }

    public function loaded(): int
    {
        return $this->loaded;
    }
}
