<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a framework CONTRACT method — a hook a subclass MUST declare whose signature the
 * framework dictates, be that an array's shape (`rules`, `schema`, `casts`) or a nullable return
 * (`Guard::user()`). Read by near-duplicate (the shared skeleton is inherent), array-return-bag (the
 * mandated array isn't a bag) and de-nulled-finder (the class cannot narrow what it must fulfil).
 */
final class ContractMethod extends Exemption
{
    public function slug(): string
    {
        return 'contract-method';
    }

    public function description(): string
    {
        return 'A framework-mandated method (`rules`/`schema`/`casts`/`Guard::user`) whose signature the framework dictates — exempt from near-duplicate, array-return-bag and de-nulled-finder.';
    }
}
