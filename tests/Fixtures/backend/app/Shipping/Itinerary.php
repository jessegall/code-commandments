<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Sins\Backend\StackedDocblock;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

#[Sinful(MemberOutOfOrder::class)]
#[Sinful(StackedDocblock::class)]
final class Itinerary
{
    /**
     * How each leg is carried, in order.
     */
    /**
     * @var list<string>
     */
    public array $legModes = [];

    public string $reference = '';

    public static int $planned = 0;

    /**
     * The relational twin `covers($leg)` would be fine; with nothing to compare, this is a question.
     */
    #[Sinful(BareStatePredicate::class)]
    public function covers(): bool
    {
        return $this->legModes !== [];
    }
}

/**
 * The FIX: the same three fields in the one fixed sequence — the static counter first, then the
 * instance state. `$planned` has MOVED UP past the two it arrived after; nothing else changed.
 */
#[Fixed(MemberOutOfOrder::class)]
final class PlannedItinerary
{
    public static int $planned = 0;

    /**
     * @var list<string>
     */
    public array $legModes = [];

    public string $reference = '';

    public function isEmpty(): bool
    {
        return $this->legModes === [];
    }
}
