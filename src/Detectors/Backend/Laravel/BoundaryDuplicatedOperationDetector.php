<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\BoundaryOperations;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two entry points on DIFFERENT kinds of boundary — a console command and an MCP tool, a controller
 * and a command — performing the same domain calls. The kind is read from the class's base type and
 * the route table, never a namespace segment; widely-used calls are infrastructure, not an operation
 * ({@see BoundaryOperations}). Points at route-actions.
 */
final class BoundaryDuplicatedOperationDetector implements Detector
{
    public function sin(): Sin
    {
        return new BoundaryDuplicatedOperation();
    }

    public function find(Codebase $codebase): array
    {
        $operations = BoundaryOperations::forCodebase($codebase);

        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $operations->twinsOf($node->enclosingClassName(), $node->enclosingFunctionName()) !== [])
            ->get();
    }
}
