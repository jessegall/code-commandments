<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\Boundary;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ParamResolvedFromParam;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ParamResolution;
use JesseGall\CodeCommandments\Backend\Detector;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Detects methods unpacking a target from a container parameter; pass the resolved object,
 * not the container plus a key.
 */
final class ParamResolvedFromParamDetector implements Detector, Exemptable
{
    /**
     * Request bases that mark a method as an HTTP/MCP boundary entry point.
     */

    public function sin(): Sin
    {
        return new ParamResolvedFromParam();
    }

    public function exemptions(): array
    {
        return [Boundary::class];
    }

    public function find(Codebase $codebase): array
    {
        $resolution = new ParamResolution();

        return $codebase
            ->whereMethodDeclaration()
            ->reject(static fn (AstNode $node): bool => self::isBoundary($codebase, $node))
            ->where(static fn (AstNode $node): bool => $resolution->unpacksTargetFromContainerParam($node, $codebase))
            ->get();
    }

    private static function isBoundary(Codebase $codebase, AstNode $node): bool
    {
        $method = $node->node;

        if (! $method instanceof ClassMethod) {
            return false;
        }

        foreach ($method->params as $param) {
            if ($param instanceof Param && self::isRequestType($codebase, $param->type)) {
                return true;
            }
        }

        return false;
    }

    private static function isRequestType(Codebase $codebase, ?object $type): bool
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Name) {
            return false;
        }

        return Exemptions::has(Boundary::class, $codebase, $type->toString());
    }
}
