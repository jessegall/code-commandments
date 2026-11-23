<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The colours a till's screen is drawn in: a list of colours, the name of a saved theme, or none for the default.
 */
final class TillSkin
{
    #[Righteous(PhantomNullable::class)]
    public function __construct(public readonly array | string | null $colours = null) {}

    /**
     * @return list<string>
     */
    public function palette(): array
    {
        return self::paletteOf($this->colours);
    }

    /**
     * @return list<string>
     */
    private static function paletteOf(array | string | null $colours): array
    {
        if (is_array($colours)) {
            return array_values($colours);
        }
        if (is_string($colours)) {
            return self::themed($colours);
        }

        return ['black', 'white'];
    }

    /**
     * @return list<string>
     */
    private static function themed(string $theme): array
    {
        return [$theme . '-dark', $theme . '-light'];
    }
}
