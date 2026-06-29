<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ParamResolution;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * A method that UNPACKS its target out of a container parameter — takes a container
 * object AND a scalar key, resolves the key against the container
 * (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`),
 * and works on the resolved target while the container is only ever packaging. The
 * caller passed both and named the key, so the caller should resolve once and pass
 * the OBJECT — and own the "not found" failure. Demand the resolved type, not an id
 * plus its container. Points at pass-the-object.
 *
 * The precision is the "pure encapsulator" test in {@see ParamResolution}: the
 * container counts only when it is used nowhere but the unpack (otherwise just cheap
 * `$owner->prop` reads). A container used as a whole object downstream — graph
 * surgery on `$graph`, a registry keying into `$this` — is a genuine co-subject, not
 * packaging, and is left alone. An HTTP/MCP boundary (a method also taking a Request)
 * is exempt too: the key arrives as a route arg and there is no caller to hand an
 * object.
 */
final class ParamResolvedFromParamDetector implements Detector
{
    /**
     * Request bases that mark a method as an HTTP/MCP boundary entry point.
     */
    private const array BOUNDARY_BASES = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Laravel\\Mcp\\Request',
    ];

    public function skill(): string
    {
        return 'pass-the-object';
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

        $name = $type->toString();

        foreach (self::BOUNDARY_BASES as $base) {
            if ($name === $base || $codebase->extends($name, $base)) {
                return true;
            }
        }

        return false;
    }
}
