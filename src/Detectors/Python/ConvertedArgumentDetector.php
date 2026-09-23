<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConvertedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A scalar parameter declared in the wrong currency — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ConvertedArgumentDetector}. The evidence is the same
 * conversion, which mypy says builds a class, filling the same parameter call after call: the function
 * knows the conversion and makes every caller keep it.
 */
final class ConvertedArgumentDetector implements Detector, RecurrenceDetector, WholeTree
{
    use BucketsByGroupKey;

    /**
     * The conversion must be what that parameter USUALLY gets. On two of nineteen sites it is those callers'
     * own business; at half or more it is the parameter's real currency.
     */
    private const float DOMINANT = 0.5;

    public function sin(): Sin
    {
        return new ConvertedArgument();
    }

    /**
     * `path:line#parameter=conversion` of the function reached — null for a call that hands no scalar
     * parameter a conversion, or reaches nothing the index resolves.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        if (! $finding instanceof ExprMatch || ! $codebase instanceof Codebase) {
            return null;
        }

        foreach ($this->scalarArguments($finding, $codebase) as $name => $argument) {
            $conversion = $finding->conversionIn($argument, $codebase)->filter(fn (string $class): bool => ! $this->isCallersOwn($finding, $codebase, $class));

            if ($conversion->isSome()) {
                return $this->slot($finding, $codebase, $name) . '=' . $conversion->unwrap();
            }
        }

        return null;
    }

    public function find(Codebase $codebase): array
    {
        $supplied = $this->supplied($codebase);
        $buckets = $this->recurringBuckets($codebase->whereCall()->get(), $codebase);
        $dominant = array_filter($buckets, fn (array $bucket): bool => count($bucket) / $supplied[explode('=', (string) $this->groupKey($bucket[0], $codebase))[0]] >= self::DOMINANT);

        return array_merge([], ...array_values($dominant));
    }

    /**
     * What $call hands each scalar parameter of the function it reaches, by the parameter's name.
     *
     * @return array<string, Expr>
     */
    private function scalarArguments(ExprMatch $call, Codebase $codebase): array
    {
        $target = $codebase->index()->targetOf($call->expr);
        $bound = $codebase->index()->argumentsAt($call->expr);

        if ($target->isNone() || $bound->isNone()) {
            return [];
        }

        $scalars = array_filter($target->unwrap()->params, static fn (Param $param): bool => $param->isScalar());

        return array_intersect_key($bound->unwrap(), array_flip(array_map(static fn (Param $param): string => $param->name, $scalars)));
    }

    /**
     * The parameter a call fills, named where its function is declared.
     */
    private function slot(ExprMatch $call, Codebase $codebase, string $name): string
    {
        return $codebase->index()->declarationOf($codebase->index()->targetOf($call->expr)->unwrap()) . '#' . $name;
    }

    /**
     * Is $class the one the call is written in — its own named constructor, which is the caller's business?
     */
    private function isCallersOwn(ExprMatch $call, Codebase $codebase, string $class): bool
    {
        return $call->module->classOf($call->expr)->isSomeAnd(
            static fn (ClassDef $own): bool => str_starts_with("{$class}.", $codebase->fullNameOf($call->module) . ".{$own->name}."),
        );
    }

    /**
     * How many call sites fill each scalar parameter — the denominator behind {@see DOMINANT}.
     *
     * @return array<string, int>
     */
    private function supplied(Codebase $codebase): array
    {
        $counts = [];

        foreach ($codebase->whereCall()->get() as $call) {
            foreach (array_keys($this->scalarArguments($call, $codebase)) as $name) {
                $slot = $this->slot($call, $codebase, $name);
                $counts[$slot] = ($counts[$slot] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
