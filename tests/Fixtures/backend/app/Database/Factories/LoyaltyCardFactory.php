<?php

namespace Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\LoyaltyCard;

/**
 * Builds loyalty cards for tests; the model names this factory back, as the framework asks of the pair.
 */
#[Righteous(NamespaceCycle::class)]
final class LoyaltyCardFactory extends Factory
{
    protected $model = LoyaltyCard::class;
}
