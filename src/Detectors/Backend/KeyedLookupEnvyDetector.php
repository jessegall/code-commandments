<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\LookupEnvy;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Feature envy through an indirect lookup — a method that uses an owned object's
 * identity as a KEY to fetch data about it through a collaborator, then reads a
 * fact back (`$this->registry->get($node->key)->reservedOutputNames`). The object
 * is being treated as a key into its own data, so the answer belongs ON it
 * (`$node->reservedOutputNames()`, `$node->isControlHandle($port)`). This is the
 * indirect form the direct iterate/query/mutate checks of FeatureEnvyDetector
 * can't see. Decided by {@see LookupEnvy} on semantic signals only. Points at
 * tell-dont-ask.
 */
final class KeyedLookupEnvyDetector implements Detector
{
    public function sin(): Sin
    {
        return new KeyedLookupEnvy();
    }

    public function find(Codebase $codebase): array
    {
        $envy = LookupEnvy::forCodebase($codebase);

        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $envy->isEnviedOwner($node))
            ->get();
    }
}
