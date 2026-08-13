<?php

namespace Shop\Ui\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A map being EXTENDED, not a record being declared: the literal spreads the declarations it was
 * handed and states what it adds to them, so its key set is not known here at all. The fields a
 * reader can see are only part of what comes back — there is no closed set of them to name.
 */
final class StyleDeclarations
{

    /**
     * @param  array<string, string>  $base
     *
     * @return array<string, string>
     */
    #[Righteous(ArrayReturnBag::class)]
    public function spanning(array $base): array
    {
        return [
            ...$base,
            'width' => 'calc(var(--span, 0) * 1px)',
            'max-width' => 'none',
            'margin-inline' => '0',
        ];
    }

}
