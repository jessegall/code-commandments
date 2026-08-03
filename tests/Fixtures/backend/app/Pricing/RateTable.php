<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Rates loaded into a static, so `for()` silently depends on someone having called `load()` first
 * and the last caller decides what every later one reads.
 */
final class RateTable
{
    /**
     * @var array<string, float>
     */
    private static array $table = [];

    /**
     * @param  array<string, float>  $rates
     */
    #[Sinful(MutableStaticState::class)]
    public static function load(array $rates): void
    {
        self::$table = $rates;
    }

    public static function for(string $region): float
    {
        return self::$table[$region] ?? 1.0;
    }
}

/**
 * The same rates, held by an instance someone constructed and passes on. Who owns them — and who
 * may change them — is written down in the signature.
 */
final class OwnedRateTable
{
    /**
     * @param  array<string, float>  $table
     */
    public function __construct(private readonly array $table) {}

    #[Fixed(MutableStaticState::class)]
    public function for(string $region): float
    {
        return $this->table[$region] ?? 1.0;
    }
}

/**
 * A static used purely as a memo: the write is guarded by the value's own absence, so asking twice
 * gives the same answer and nothing a caller can observe has changed.
 */
final class RoundedRates
{
    /**
     * @var array<string, float>
     */
    private static array $rounded = [];

    #[Righteous(MutableStaticState::class)]
    public static function of(string $region, float $rate): float
    {
        if (! isset(self::$rounded[$region])) {
            self::$rounded[$region] = round($rate, 2);
        }

        return self::$rounded[$region];
    }
}
