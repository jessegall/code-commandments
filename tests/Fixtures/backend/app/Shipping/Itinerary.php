<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Sinful;

#[Sinful(MemberOutOfOrder::class)]
final class Itinerary
{
    /** @var list<string> */
    public array $legModes = [];

    public string $reference = '';

    public static int $planned = 0;
}
