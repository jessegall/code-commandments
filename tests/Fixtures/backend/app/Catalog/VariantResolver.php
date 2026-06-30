<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\PositionalTupleReturn;

use JesseGall\CodeCommandments\Testing\Sinful;

final class VariantResolver
{
    /**
     * @param  list<string>  $skus
     * @param  list<string>  $known
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    #[Sinful(PositionalTupleReturn::class)]
    public function reconcile(array $skus, array $known): array
    {
        $matched = array_values(array_intersect($skus, $known));
        $missing = array_values(array_diff($skus, $known));
        $surplus = array_values(array_diff($known, $skus));

        return [$matched, $missing, $surplus];
    }
}
