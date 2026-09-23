<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\Backend\Config\RepeatedNamedCallConfig;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RepeatedNamedCall;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * One `**changes` function called with the same keyword, built the same way, at site after site — the
 * twin of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\RepeatedNamedCallDetector}.
 * Only a call the index resolves counts; a receiver it cannot type is never guessed.
 */
final class RepeatedNamedCallDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;
    use RepeatedNamedCallConfig;

    public function sin(): Sin
    {
        return new RepeatedNamedCall();
    }

    /**
     * `path:line#keyword=shape,…` of the function reached — null for a call that builds nothing under a
     * keyword, or reaches no `**` function the index resolves.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        if (! $finding instanceof ExprMatch || ! $codebase instanceof Codebase) {
            return null;
        }

        $keywords = $finding->expr->keywordArguments();
        $target = $codebase->index()->targetOf($finding->expr);

        if (! array_any($keywords, static fn (Expr $keyword): bool => $keyword->get('value')->isConstruction())) {
            return null;
        }

        if (! $target->isSomeAnd(static fn (FunctionDef $function): bool => $function->takesKeywordRest())) {
            return null;
        }

        $slots = array_map(static fn (Expr $keyword): string => $keyword->get('name') . '=' . $keyword->get('value')->constructionShape(), $keywords);
        sort($slots);

        return $codebase->index()->declarationOf($target->unwrap()) . '#' . implode(',', $slots);
    }

    public function find(Codebase $codebase): array
    {
        return array_merge([], ...$this->recurringBuckets($codebase->whereCall()->get(), $codebase, $this->threshold));
    }
}
