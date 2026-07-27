<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;

/**
 * The single home of "is this array a SERIALIZATION of a type that already exists?" — the line the
 * value-objects rule turns on. An array whose every value is read off ONE already-typed object is
 * that object's wire/row shape (a second type would only be flattened into the identical array at
 * the same call site); an array assembled from several places, or from a source we cannot vouch for
 * as a value — `bagFrom(Request $request)` — is the unborn type the rule means.
 */
final class Projection
{
    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Is this array literal the projection of one already-typed object?
     */
    public function ofTypedObject(AstNode $node): bool
    {
        $source = $node->arrayProjectionSource();

        return match (true) {
            $source === null => false,
            $source === 'this' => true, // the enclosing class is the type the array projects
            default => $this->isTypedObject($node->enclosingParamType($source)),
        };
    }

    /**
     * Does this parameter type name an OBJECT we can vouch for as a value? A class whose own fields
     * are values is a type the array can be the shape OF. A scalar or an `array` is not: `row(array
     * $data)` re-keying a bag into another bag is the unborn type itself, and a vendor/service type
     * is one we cannot vouch for at all.
     */
    private function isTypedObject(?Node $type): bool
    {
        return TypeName::class($type) !== null && $this->codebase->isValueType($type);
    }
}
