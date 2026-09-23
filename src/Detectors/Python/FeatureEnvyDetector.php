<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\FeatureEnvy;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\FeatureEnvy as FeatureEnvySin;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * Exiled behaviour — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector}, decided by {@see FeatureEnvy}.
 */
final class FeatureEnvyDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new FeatureEnvySin();
    }

    public function find(Codebase $codebase): array
    {
        $envy = new FeatureEnvy($codebase);

        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $envy->enviedParameter($match->node, $match->module)->isSome())
            ->get();
    }
}
