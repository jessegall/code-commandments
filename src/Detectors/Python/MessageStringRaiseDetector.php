<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MessageStringRaise;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A builtin that names no failure raised with its description written at the raise — the Python twin
 * of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector} and
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MessageAtThrowDetector} together.
 */
final class MessageStringRaiseDetector implements Detector
{
    public function sin(): Sin
    {
        return new MessageStringRaise();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node instanceof Raise && $node->isGenericWithMessage())
            ->get();
    }
}
