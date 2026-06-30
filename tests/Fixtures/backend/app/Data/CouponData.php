<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\DataMethodHintCollision;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A discount coupon with public (non-promoted) properties and validation rules.
 * Here the `@method` collides with the real static `rules()` — the collision sin
 * is not specific to factories or to promoted-constructor classes.
 *
 * @method static array rules()
 */
#[Sinful(DataMethodHintCollision::class)]
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

/**
 * A voucher whose `@method` hint describes only the invisible magic `::from()`,
 * never the concrete `fromCode()` factory it dispatches to — so nothing collides.
 *
 * @method static static from(string $code)
 */
#[Righteous(DataMethodHintCollision::class)]
final class VoucherData extends Data
{
    public string $code;

    public static function fromCode(string $code): static
    {
        return new static(code: $code);
    }
}
