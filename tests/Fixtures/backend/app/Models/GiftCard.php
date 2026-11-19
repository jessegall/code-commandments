<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RepeatedQueryChain;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * A prepaid gift card, spent down at checkout and topped up at the till.
 */
class GiftCard extends Model
{
    protected $fillable = ['code', 'balance_cents'];

    #[Fixed(RepeatedQueryChain::class)]
    public static function lockedFor(int $cardId): self
    {
        return self::query()->whereKey($cardId)->lockForUpdate()->sole();
    }
}
