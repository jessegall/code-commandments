<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `Data` computed slot whose getter hand-flattens a value object into a wire array —
 * `get => ['amount' => $this->order->priceInCents, 'currency' => $this->order->currency]`. The honest
 * type is erased to `array` and the shaping is copy-pasted onto every page; a `#[WithTransformer]` (with
 * a matching `#[TypeScriptType]`) should own the serialized shape declaratively. Points at page-objects.
 *
 * The discriminator is the receiver's resolved type — a single value object flattened, not a composite:
 * `$this`-composition resolves to the `Data` itself and a two-receiver body never shares, so both are
 * left alone by {@see SpatieDataNode::flattensValueObjectToArray()} without a name or a hardcoded list.
 *
 * A public slot's wire array reaches this sin three ways — a property-hook getter
 * (`public array $x { get => [ … ]; }`), a `#[Computed]` method returning the array, or a constructor
 * assignment to a public declared slot (`$this->x = [ … ]`) — all the same flatten, so all three are
 * queried and gated by the identical {@see SpatieDataNode::flattensValueObjectToArray()} predicate.
 */
final class ManualOutputTransformDetector implements Detector
{
    public function sin(): Sin
    {
        return new ManualOutputTransform();
    }

    public function find(Codebase $codebase): array
    {
        $flattensValueObject = static fn (SpatieDataNode $node): bool =>
            $node->isDataClass() && $node->flattensValueObjectToArray();

        return [
            ...$codebase->whereGetterHook()->where($flattensValueObject)->get(),
            ...$codebase->whereMethodDeclaration()
                ->where(static fn (AstNode $node): bool => $node->hasAttribute('Computed'))
                ->where($flattensValueObject)
                ->get(),
            ...$codebase->whereAssign()->where($flattensValueObject)->get(),
        ];
    }
}
