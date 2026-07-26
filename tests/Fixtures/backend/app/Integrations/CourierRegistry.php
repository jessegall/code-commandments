<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\StackedDocblock;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Keyed store of couriers, resolved by the courier binding to turn a configured name into an API.
 */
/**
 * Written twice, read once — PHP shows a reader only this block.
 */
#[Sinful(MemberAfterMethod::class)]
#[Sinful(StackedDocblock::class)]
final class CourierRegistry
{
    public function preferred(): CourierApi
    {
        return $this->couriers['preferred'];
    }

    /**
     * @var array<string, CourierApi>
     */
    private array $couriers = [];
}
