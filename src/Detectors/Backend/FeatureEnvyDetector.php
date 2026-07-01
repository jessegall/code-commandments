<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy as FeatureEnvySin;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\FeatureEnvy;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Exiled behaviour (feature envy) — a method that reaches THROUGH one other owned
 * object's structure, iterating its collection, to do work that belongs ON that
 * object (`$node->edges()`, not `EdgeDetector::detect($node)`). Decided by
 * {@see FeatureEnvy} on semantic signals only (no name/query lists): it loops one
 * owned parameter's collection, accesses that object more than its own `$this`
 * state, constructs nothing, and isn't a polymorphic contract method. A policy /
 * Strategy over the object's flat scalar fields is the documented exception.
 * Points at tell-dont-ask.
 */
final class FeatureEnvyDetector implements Detector
{
    public function sin(): Sin
    {
        return new FeatureEnvySin();
    }

    public function find(Codebase $codebase): array
    {
        $envy = FeatureEnvy::forCodebase($codebase);

        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $envy->isEnviedOwner($node))
            ->get();
    }
}
