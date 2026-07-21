<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A Data construction handing `''` to a slot typed as a required, non-nullable `string` — the
 * envelope's TYPES stay honest while its VALUES are manufactured. Narrow by design: `0`/`false` are
 * ordinary domain values, and a nullable slot already admits absence. Points at type-honesty.
 */
final class PlaceholderFilledDataDetector implements Detector
{
    public function sin(): Sin
    {
        return new PlaceholderFilledData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->where(static fn (SpatieDataNode $node): bool => $node->isNewData())
            ->where(static fn (AstNode $node): bool => self::fillsAStringSlotWithNothing($codebase, $node))
            ->get();
    }

    /**
     * Does a slot typed as a NON-NULLABLE STRING receive `''`?
     *
     * The type is what makes this readable as a lie: the class promises a string that is always
     * present, and the caller hands it the absence of one. Deliberately narrow — `0` and `false` are
     * ordinary domain values (`fill: false`, `rate: 0`), and a nullable slot already says "may be
     * missing", so `null` there is honest.
     */
    private static function fillsAStringSlotWithNothing(Codebase $codebase, AstNode $node): bool
    {
        $params = AstNode::constructorParamsOf($codebase->classNamed($node->newClassName())->node);

        if ($params === []) {
            return false;
        }

        foreach ($node->arguments() as $index => $argument) {
            $value = $argument->value;

            if (! $value instanceof \PhpParser\Node\Scalar\String_ || $value->value !== '') {
                continue;
            }

            if (self::promisesAString(self::slotFor($params, $argument, $index))) {
                return true;
            }
        }

        return false;
    }

    /** The constructor parameter an argument targets — by NAME when named, else by position. */
    private static function slotFor(array $params, \PhpParser\Node\Arg $argument, int $index): ?\PhpParser\Node\Param
    {
        if ($argument->name === null) {
            return $params[$index] ?? null;
        }

        foreach ($params as $param) {
            if (($param->var->name ?? null) === $argument->name->toString()) {
                return $param;
            }
        }

        return null;
    }

    /** Is this slot a required, non-nullable `string` — a promise that a real value is always there? */
    private static function promisesAString(?\PhpParser\Node\Param $param): bool
    {
        return $param !== null
            && $param->default === null
            && $param->type instanceof \PhpParser\Node\Identifier
            && $param->type->toString() === 'string';
    }
}
