<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedCondition;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sends crates out of one bay. The condition below asks the crate two things and the bay a third, and
 * a reader has to hold all three in mind to see that it means "ready to load". Below it, the FIX: the
 * crate answers that question by its name, and each object answers for its own part of it.
 */
final class BayDispatcher
{
    /**
     * @var list<string>
     */
    private array $sent = [];

    public function __construct(private readonly LoadingBay $bay) {}

    #[Sinful(UnnamedCondition::class)]
    public function dispatch(Crate $crate): void
    {
        if ($crate->isPacked() && $crate->hasLabel() && $this->bay->isFree()) {
            $this->bay->take($crate);
            $this->sent[] = $crate->reference;
        }
    }

    #[Fixed(UnnamedCondition::class)]
    public function dispatchTold(Crate $crate): void
    {
        if ($crate->readyFor($this->bay)) {
            $this->bay->take($crate);
            $this->sent[] = $crate->reference;
        }
    }

    /**
     * @return list<string>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
