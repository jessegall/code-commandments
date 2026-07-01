<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\InArrayMirrorsEnum;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\Enums;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * `in_array($x, ['a', 'b', …])` whose literals ARE an existing backed enum's case
 * values — testing membership of a set the type already seals. Use the enum:
 * `$x instanceof SomeEnum` is moot; a case group method (`$case->isFoo()`) or
 * `SomeEnum::tryFrom($x)` says it honestly. Points at enums-with-behaviour.
 */
final class InArrayMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new InArrayMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        $enums = Enums::casesByEnum($codebase);

        return $codebase
            ->whereFunction('in_array')
            ->where(static fn (AstNode $node): bool => Enums::mirroredBy($node->argumentArrayLiterals(1), $enums))
            ->get();
    }
}
