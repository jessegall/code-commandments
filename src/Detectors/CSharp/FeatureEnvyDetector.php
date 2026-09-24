<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * Exiled behaviour — the twin of the backend's and Python's feature-envy rules, decided by
 * {@see NodeMatch::enviedParameter}.
 */
final class FeatureEnvyDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new FeatureEnvy();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->enviedParameter($codebase)->isSome())
            ->get();
    }
}
