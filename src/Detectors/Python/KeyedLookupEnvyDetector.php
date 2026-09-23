<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\LookupEnvy;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\KeyedLookupEnvy;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * Feature envy through a keyed lookup — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\KeyedLookupEnvyDetector}, decided by {@see LookupEnvy}.
 */
final class KeyedLookupEnvyDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new KeyedLookupEnvy();
    }

    public function find(Codebase $codebase): array
    {
        $envy = new LookupEnvy($codebase);

        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $envy->isEnvious($match->node, $match->module))
            ->get();
    }
}
