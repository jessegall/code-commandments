<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Sins\Frontend\TypeScript\FalselyOptionalField;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Ts\Node\FieldDecl;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A field declared optional that is INITIALISED where it is declared. The initialiser is the proof:
 * the value exists from construction, so the `?` claims an absence the object never has, and every
 * `?.` and `??` written downstream defends a case that cannot occur.
 */
final class FalselyOptionalFieldDetector implements Detector
{
    public function sin(): Sin
    {
        return new FalselyOptionalField();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FieldDecl && $match->node->isOptional())
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FieldDecl && $match->node->initializer !== null)
            ->reject(static fn (NodeMatch $match): bool => $match->node instanceof FieldDecl && $match->node->initializer->isNullLiteral())
            ->get();
    }
}
