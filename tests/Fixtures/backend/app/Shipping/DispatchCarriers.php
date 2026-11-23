<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicatedConfigDefault;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The carriers a depot hands its parcels to, in the order the dispatch config lists them.
 */
final class DispatchCarriers
{
    /**
     * @return list<string>
     */
    #[Sinful(DuplicatedConfigDefault::class)]
    public function offered(): array
    {
        $carriers = (array) config('dispatch.carriers', ['dhl']);

        return array_values(array_filter($carriers, 'is_string'));
    }

    #[Fixed(DuplicatedConfigDefault::class)]
    public function first(): string
    {
        $carriers = (array) config('dispatch.carriers');

        return (string) reset($carriers);
    }
}
