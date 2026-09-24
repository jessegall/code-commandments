<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\CSharp\RepeatedNamedCall;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The same `with` copy of one record — the same members set to the same constants — at two or more sites: the
 * C# twin of the PHP and Python repeated-named-call rules, whose copy-with-changes call C# writes as `with`.
 * A test varies a fixture this way on purpose — the copy is how it says what differs — so tests are left alone.
 */
final class RepeatedNamedCallDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    public function sin(): Sin
    {
        return new RepeatedNamedCall();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        if (! $finding instanceof NodeMatch || $finding->node->constantChanges() === []) {
            return null;
        }

        return $finding->node->type?->name . '#' . implode(',', $finding->node->constantChanges());
    }

    public function find(Codebase $codebase): array
    {
        $copies = $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->constantChanges() !== [])
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();

        return array_merge([], ...$this->recurringBuckets($copies, $codebase));
    }
}
