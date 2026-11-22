<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Database\Factories\GiftCardFactory;

/**
 * A gift card a customer can spend at the till, made in tests by the factory it names.
 */
#[Righteous(NamespaceCycle::class)]
final class GiftCard extends Model
{
    protected static function newFactory(): GiftCardFactory
    {
        return GiftCardFactory::new();
    }
}
