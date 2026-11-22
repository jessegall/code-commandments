<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\Events\LoyaltyCardIssued;

/**
 * A customer's loyalty card, which announces itself when it is created.
 */
final class LoyaltyCard extends Model
{
    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => LoyaltyCardIssued::class,
    ];
}
