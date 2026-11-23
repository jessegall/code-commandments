<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The VAT bands, built once and kept: the guard on the band map stops the block that also fills the list of band
 * names, so both are filled once.
 */
final class VatBands
{
    /**
     * @var array<string, int>|null
     */
    private static ?array $percentages = null;

    /**
     * @var list<string>
     */
    private static array $names = [];

    /**
     * @return array<string, int>
     */
    #[Righteous(MutableStaticState::class)]
    public function percentages(): array
    {
        if (self::$percentages !== null) {
            return self::$percentages;
        }
        self::$percentages = [];
        foreach (['standard' => 21, 'reduced' => 9] as $band => $percentage) {
            self::$names[] = $band;
            self::$percentages[$band] = $percentage;
        }

        return self::$percentages;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $this->percentages();

        return self::$names;
    }
}
