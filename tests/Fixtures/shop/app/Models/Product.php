<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Shop\ValueObjects\Money;

class Product extends Model
{
    protected $fillable = ['name', 'price_cents'];

    public function price(): Money
    {
        return Money::ofCents($this->price_cents);
    }

    /**
     * The righteous home for the mutation: a named method on the model that owns
     * the `update([...])` — `$this`, so the call-site detector leaves it alone.
     */
    public function markPublished(): void
    {
        $this->update([
            'published' => true,
            'published_at' => now(),
        ]);
    }
}
