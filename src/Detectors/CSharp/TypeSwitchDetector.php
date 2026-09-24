<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\TypeSwitch;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A switch whose cases ask which of two or more of the codebase's own types a value is — the C# twin of the
 * PHP and Python type-switch rules. The value switched on must be of a type the codebase owns, since that
 * type is where the member goes — an `object`, or a library's `Exception`, has no such home; types the
 * codebase does not own cannot take the member either. A switch whose every arm builds a new object is a
 * mapper, which belongs with the type it builds; a union whose cases are declared inside it is meant to be
 * switched over, like an enum; a named constructor is where a value's type decides how an object is made.
 */
final class TypeSwitchDetector implements Detector
{
    public function sin(): Sin
    {
        return new TypeSwitch();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => count(array_unique($node->switchedTypes())) >= 2)
            ->where(static fn (NodeMatch $match): bool => array_all($match->node->switchedTypes(), $codebase->declaresType(...)))
            ->where(static fn (NodeMatch $match): bool => $codebase->declaresType($match->node->switchedSubjectType()))
            ->reject(static fn (NodeMatch $match): bool => $match->node->isTranslatingEveryArm())
            ->reject(static fn (NodeMatch $match): bool => $match->node->isSwitchOverOwnCases())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinNamedConstructor())
            ->get();
    }
}
