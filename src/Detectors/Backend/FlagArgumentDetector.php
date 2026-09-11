<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A method whose whole body is `if ($flag) {…} else {…}` on one of its own `bool` parameters — two
 * methods sharing a name, and a call site that reads `render($order, true)`. The flag is not data
 * the method works with; it is the caller's decision about WHICH method it wanted, flattened into a
 * truth value. Its disguise is a parameter widened to `?T = null` whose ABSENCE picks the other path
 * (`attributes()` vs `attributes(Slot::class)`) — the call site then says nothing at all. Points at
 * behaviour-per-method.
 */
final class FlagArgumentDetector implements Detector
{
    public function sin(): Sin
    {
        return new FlagArgument();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            // A constructor's parameters are the object's own fields being born, never a switch.
            ->reject(static fn (AstNode $node): bool => $node->isConstructorDeclaration())
            ->where(static fn (AstNode $node): bool => $node->switchesEntirelyOnABoolParam() || $node->switchesEntirelyOnAnAbsentParam())
            ->get();
    }
}
