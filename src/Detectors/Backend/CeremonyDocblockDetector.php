<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\CeremonyDocblock;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A docblock that only restates the typed signature — `@param Type $x` with no
 * description on an already-typed parameter, plus maybe a bare `@return Type`.
 * It adds nothing the signature doesn't already say and rots out of sync; delete
 * it. A real description or a generic/shape refinement (`array<…>`, `array{…}`,
 * a union) earns the block its keep. Points at documentation.
 */
final class CeremonyDocblockDetector implements Detector
{
    public function sin(): Sin
    {
        return new CeremonyDocblock();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->hasCeremonyDocblock())
            ->get();
    }
}
