<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Database\Factories\LoyaltyCardFactory;
use Shop\Events\LoyaltyCardIssued;

/**
 * A customer's loyalty card, which announces itself when it is created.
 */
#[UseFactory(LoyaltyCardFactory::class)]
#[Righteous(NamespaceCycle::class)]
final class LoyaltyCard extends Model
{
    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => LoyaltyCardIssued::class,
    ];
}
