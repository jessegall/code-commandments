<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Spatie\HydrationSlot;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use PhpParser\Node;

/** The Spatie decorator's answers about how Data is constructed and hydrated, at every expression and field. */
final class SpatieConstructions implements Question
{
    public function name(): string
    {
        return 'spatieconstructions';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            $data = $codebase->wrap($node, $file, SpatieDataNode::class);
            if ($node instanceof Node\Expr) {
                $slot = $data->hydrationSlot()->mapOr(null, static fn (HydrationSlot $slot): array => [
                    $slot->ownerFqcn, $slot->property, $slot->declaredType, $slot->isCollection, $slot->elementType, $slot->valueInList, $slot->destHasCast,
                ]);
                $factory = $data->mappedFactory();
                yield [$node, 'expression', [
                    'slot' => $slot,
                    'isHandedConstructedData' => $data->isHandedConstructedData(),
                    'isPerItemHydration' => $data->isPerItemHydration(),
                    'isInlineProjection' => $data->isInlineProjection(),
                    'isConditionalConstruction' => $data->isConditionalConstruction(),
                    'isWithinTolerantCatch' => $data->isWithinTolerantCatch(),
                    'isKeyedMapAssignment' => $data->isKeyedMapAssignment(),
                    'isEnumUnwrapIntoItsOwnSlot' => $data->isEnumUnwrapIntoItsOwnSlot(),
                    'fromArgIsArrayLiteral' => $data->fromArgIsArrayLiteral(),
                    'constructedClass' => $data->constructedClass(),
                    'hydratesAnAutoBuiltSlot' => $data->hydratesAnAutoBuiltSlot(),
                    'hydrationSlotHasCast' => $data->hydrationSlotHasCast(),
                    'mappedFactory' => $factory === null ? null : [$factory->class, $factory->method, $factory->returnsType, $factory->closesOverContext],
                    'mappedFactoryDerivesElement' => $data->mappedFactoryDerivesElement(),
                    'constructsNativeCastValue' => $data->constructsNativeCastValue(),
                    'hasSingleArgument' => $data->hasSingleArgument(),
                    'slotAcceptsNativeCast' => $data->slotAcceptsNativeCast(),
                    'isHandKeyRemap' => $data->isHandKeyRemap(),
                    'isRedundantToArrayRoundtrip' => $data->isRedundantToArrayRoundtrip(),
                ]];
            }
            if ($node instanceof Node\Param || $node instanceof Node\Stmt\Property) {
                yield [$node, 'field', $data->alwaysHandBuiltAtConstruction()];
            }
        }
    }
}
