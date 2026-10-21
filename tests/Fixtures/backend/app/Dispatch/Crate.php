<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedCondition;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * One crate waiting to be loaded. It answers for its own readiness, which is what lets a caller ask
 * the question by its name instead of working it out from three smaller ones.
 */
final readonly class Crate
{
    public function __construct(public string $reference, private bool $packed, private bool $labelled) {}

    public function isPacked(): bool
    {
        return $this->packed;
    }

    public function hasLabel(): bool
    {
        return $this->labelled;
    }

    #[Fixed(UnnamedCondition::class)]
    public function readyFor(LoadingBay $bay): bool
    {
        return $this->packed && $this->labelled && $bay->isFree();
    }
}
