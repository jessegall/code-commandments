<?php

namespace Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\GiftCard;

/**
 * Builds gift cards for tests; the model hands this factory out from its own newFactory().
 */
#[Righteous(NamespaceCycle::class)]
final class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;
}
