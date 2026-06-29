<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Returning a multi-field, string-keyed array literal — a structured bag that
 * should be a typed value object. Points at value-objects.
 *
 * A one-field wrapper (`['ok' => $x]`) and a list (`[1, 2, 3]`) are left alone:
 * the smell is a named-field record travelling as a loose array. Framework
 * boundary classes return arrays by contract (a FormRequest's `rules()`, an MCP
 * tool's / request's schema), so they're excluded.
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

    public function skill(): string
    {
        return 'value-objects';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->stringKeyCount() >= 2)
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->reject(static fn (AstNode $node): bool => $node->hasNestedArrayValue())
            ->reject(static fn (AstNode $node): bool => self::isBoundary($codebase, $node->enclosingClassName()))
            ->reject(static fn (AstNode $node): bool => self::isModelCasts($codebase, $node))
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
