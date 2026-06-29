<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Whether the IoC container brings a class to life — and so fills its
 * constructor with dependencies. A heuristic over the codebase, shared by the
 * detectors that care whether constructor injection is available.
 */
final class Container
{
    /**
     * Is $class resolved by the container (injected somewhere, or never built by
     * hand)? False for null / an unknown class.
     */
    public static function resolves(Codebase $codebase, ?string $class): bool
    {
        return $class !== null
            && (self::injectedAsDependency($codebase, $class) || self::neverInstantiatedByHand($codebase, $class));
    }

    /**
     * Is the class type-hinted as a constructor dependency somewhere?
     */
    private static function injectedAsDependency(Codebase $codebase, string $class): bool
    {
        return $codebase->whereParamType($class)
            ->where(static fn (AstNode $node): bool => $node->enclosingFunctionName() === '__construct')
            ->count() > 0;
    }

    /**
     * Is the class never instantiated with `new` anywhere?
     */
    private static function neverInstantiatedByHand(Codebase $codebase, string $class): bool
    {
        return $codebase->whereNew($class)->count() === 0;
    }
}
