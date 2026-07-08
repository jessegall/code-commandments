<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\RepeatedCallHelper;

final class RepeatedGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'repeated-guard',
            skill: RepeatedCallHelper::class,
            description: "The SAME compound guard condition recurs in ≥2 places — the same check spelled differently (inline reaches vs locals) or reordered still counts, so a copied condition has no name",
            rule: "Promote a recurring compound guard to a named predicate. The same condition — however its conjuncts are ordered, and whether it reads `\$obj->x` inline or a local aliased from it — belongs in ONE named method.",
            suggestion: "Extract the repeated condition into a named boolean method and call THAT at each site.",
        );
    }
}
