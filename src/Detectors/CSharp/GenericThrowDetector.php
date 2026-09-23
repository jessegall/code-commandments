<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\GenericThrow;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An exception that names no failure thrown with its description written at the throw — the C# twin of
 * the backend's generic-exception and message-at-throw rules and Python's message-string raise. The
 * `Argument…Exception` family names a bad argument and carries its parameter, as .NET prescribes, and
 * is left alone; so is a test project, whose fakes throw one to stand in for any failure at all.
 */
final class GenericThrowDetector implements Detector
{
    public function sin(): Sin
    {
        return new GenericThrow();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->isGenericThrowWithMessage())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();
    }
}
