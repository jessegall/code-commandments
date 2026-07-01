<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a framework CONTRACT method — a hook a subclass MUST declare whose shape/array
 * return the framework dictates (`rules`, `schema`, `casts`). Read by near-duplicate (the shared
 * skeleton is inherent) and array-return-bag (the mandated array isn't a bag).
 */
final class ContractMethod extends Exemption
{
    public function slug(): string
    {
        return 'contract-method';
    }

    public function description(): string
    {
        return 'A framework-mandated method (`rules`/`schema`/`casts`) whose shape/array-return the framework dictates — exempt from near-duplicate and array-return-bag.';
    }
}
