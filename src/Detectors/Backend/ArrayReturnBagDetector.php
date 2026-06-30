<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Returning a multi-field, string-keyed array literal — a structured bag that
 * should be a typed value object. Points at value-objects.
 *
 * A one-field wrapper (`['ok' => $x]`) and a list (`[1, 2, 3]`) are left alone:
 * the smell is a named-field record travelling as a loose array. Several shapes are
 * exempt because the array isn't a bag the author chose:
 *  - framework boundary classes return arrays by contract (a FormRequest's
 *    `rules()`, an MCP tool's / request's schema; an Eloquent `casts()`);
 *  - a `toArray()`/`toValues()` SELF-SERIALIZER — every value a `$this->field` read
 *    — turns a typed object into a persistence/presentation shape (skill-sanctioned);
 *  - a JSON-Schema / external-contract skeleton (`'type' => 'object'` + `properties`/
 *    `enum`/…) — a recursive open-ended spec serialized to a provider, not a fixed bag;
 *  - a method that OVERRIDES an ancestor (a parent class or interface, incl. a
 *    vendor one) whose `array` return it inherits and cannot change.
 */
final class ArrayReturnBagDetector implements Detector
{
    /**
     * Framework boundary bases whose subclasses return arrays by contract.
     */
    private const array BOUNDARY_BASES = [
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Laravel\\Mcp\\Request',
        'Laravel\\Mcp\\Server\\Tool',
    ];

    private const string MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    public function sin(): Sin
    {
        return new ArrayReturnBag();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->stringKeyCount() >= 2)
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->reject(static fn (AstNode $node): bool => $node->hasNestedArrayValue())
            ->reject(static fn (AstNode $node): bool => $node->looksLikeJsonSchema())
            ->reject(static fn (AstNode $node): bool => $node->isSelfProjectionArray())
            ->reject(static fn (AstNode $node): bool => self::isBoundary($codebase, $node->enclosingClassName()))
            ->reject(static fn (AstNode $node): bool => self::isModelCasts($codebase, $node))
            ->reject(static fn (AstNode $node): bool => $codebase->overridesMethod($node->enclosingClassName(), $node->enclosingFunctionName() ?? ''))
            ->get();
    }

    private static function isBoundary(Codebase $codebase, ?string $class): bool
    {
        foreach (self::BOUNDARY_BASES as $base) {
            if ($codebase->extends($class, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Eloquent's `casts()` hook returns a config map (`['col' => CastClass::class]`)
     * the framework reads as a raw array — it cannot be a value object. The `casts`
     * name is the framework's contract, gated on the class actually being a Model.
     */
    private static function isModelCasts(Codebase $codebase, AstNode $node): bool
    {
        return $node->enclosingFunctionName() === 'casts'
            && $codebase->extends($node->enclosingClassName(), self::MODEL);
    }
}
