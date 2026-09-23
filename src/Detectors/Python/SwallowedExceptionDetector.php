<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\SwallowedException;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A handler that catches everything and makes it vanish — the Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\SwallowCatchDetector}. A handler that names the
 * failure it expects and passes has decided what that failure means, and is left alone.
 */
final class SwallowedExceptionDetector implements Detector
{
    public function sin(): Sin
    {
        return new SwallowedException();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node instanceof ExceptHandler)
            ->where(static fn (NodeMatch $match): bool => $match->node->isBroad())
            ->where(static fn (NodeMatch $match): bool => $match->node->swallows())
            ->get();
    }
}
