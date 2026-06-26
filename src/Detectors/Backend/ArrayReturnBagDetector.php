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
 * boundary classes (a FormRequest's `rules()`) return arrays by contract, so
 * they're excluded.
 */
final class ArrayReturnBagDetector implements Detector
{
    private const string FORM_REQUEST = 'Illuminate\\Foundation\\Http\\FormRequest';

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
            ->reject(static fn (AstNode $node): bool => $codebase->extends($node->enclosingClassName(), self::FORM_REQUEST))
            ->get();
    }
}
