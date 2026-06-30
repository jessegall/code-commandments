<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Detectors\Backend\DataMethodHintCollisionDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A discount coupon with public (non-promoted) properties and validation rules.
 * Here the `@method` collides with the real static `rules()` — the collision sin
 * is not specific to factories or to promoted-constructor classes.
 *
 * @method static array rules()
 */
#[Sinful(DataMethodHintCollisionDetector::class)]
final class CouponData extends Data
{
    public string $code;

    public int $percentOff;

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'percentOff' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
