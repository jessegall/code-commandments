<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A `?T` finder whose every caller de-nulls the result (`finder()?->…`,
 * `=== null`, `?? default`). The absence is being decided at every call site
 * instead of at the source: model it in the type — resolve-or-throw if presence
 * is assumed, an `Option<T>` for a genuine miss, an empty/Null-Object otherwise.
 * Points at absence.
 *
 * Measure-and-suppress via the call graph: a `?T` finder with NO resolved callers
 * is unknown (not flagged), and one with a caller that uses the value raw is
 * left alone — only "every caller checks it" is the lie worth surfacing.
 */
final class DeNulledFinderDetector implements Detector
{
    public function skill(): string
    {
        return 'absence';
    }

    public function find(Codebase $codebase): array
    {
        $index = $codebase->index();
        $sins = [];

        foreach ($index->nullableObjectFinders() as $finder) {
            $class = $finder->enclosingClassName();
            $method = $finder->enclosingFunctionName();

            if ($class === null || $method === null) {
                continue;
            }

            $callers = $index->callersOf($class, $method);

            if ($callers === []) {
                continue;
            }

            if (array_all($callers, static fn (NodeMatch $caller): bool => $caller->resultIsDeNulled())) {
                $sins[] = $finder;
            }
        }

        return $sins;
    }
}
