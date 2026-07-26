<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\TypeName;
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
            ->where(static fn (AstNode $node) => self::fillsAStringSlotWithNothing($codebase, $node))
            ->get();
    }

    /**
     * Does a slot typed as a required, non-nullable `string` receive `''`? The TYPE is what makes this
     * readable as a lie: the class promises a value that is always there, and the caller hands it none.
     */
    private static function fillsAStringSlotWithNothing(Codebase $codebase, AstNode $node): bool
    {
        $params = AstNode::constructorParamsOf($codebase->classNamed($node->newClassName())->node);

        foreach ($node->arguments() as $index => $argument) {
            if (new AstNode($argument->value)->isEmptyString()
                && TypeName::promisesScalar(AstNode::paramForArgument($params, $argument, $index), 'string')
            ) {
                return true;
            }
        }

        return false;
    }
}
