<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\Concerns\BelongsToOpenTill;
use Shop\Tills\OpenTill;

/**
 * One cashier's shift on a till, from opening float to closing count.
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable(['till_number', 'opened_at', 'float_cents'])]
class TillShift extends Model
{
    use BelongsToOpenTill;

    #[Righteous(ContainerReach::class)]
    protected static function booted(): void
    {
        static::creating(static function (TillShift $shift): void {
            $shift->till_number ??= app(OpenTill::class)->number();
        });
    }
}
