<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\MutableValueObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A record that changes after construction — a `set` accessor on it, or a write to its own state outside a
 * constructor — the C# twin of the PHP mutable-value-object rule. A record is declared as a value, so the
 * declaration is the evidence the type is meant to be one. A setter an interface or a base class demands is
 * the contract's, not the record's.
 */
final class MutableValueObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableValueObject();
    }

    public function find(Codebase $codebase): array
    {
        $setters = $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('SetAccessorDeclaration'))
            ->where(static fn (NodeMatch $match): bool => $match->isRecordSetter())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinOverride())
            ->get();

        $writes = $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->isWrite())
            ->where(static fn (NodeMatch $match): bool => $match->isWritingRecordState())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinOverride())
            ->get();

        return [...$setters, ...$writes];
    }
}
