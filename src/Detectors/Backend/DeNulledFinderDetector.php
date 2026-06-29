<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use Closure;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Sharded;

/**
 * A `?T` finder whose result TRAVELS and is de-nulled at every stop — checked
 * (`finder()?->…`, `=== null`, `?? default`) at two or more call sites. The
 * absence is being re-decided everywhere the value lands instead of at the
 * source: model it in the type — resolve-or-throw if presence is assumed, an
 * `Option<T>` for a genuine miss, an empty/Null-Object otherwise. Points at absence.
 *
 * Blast radius via the call graph: a finder with no resolved callers is unknown
 * (not flagged), and a SINGLE local caller that checks it on the spot is an
 * honest null (not flagged). Only when the `?T` reaches ≥2 sites that each guard
 * it — the "every caller re-checks the same value" lie — is it worth surfacing.
 */
final class DeNulledFinderDetector implements Sharded
{
    private const int TRAVELS = 2;

    public function skill(): string
    {
        return 'absence';
    }

    public function find(Codebase $codebase): array
    {
        return array_values(array_filter(
            $this->candidates($codebase),
            static fn (NodeMatch $finder): bool => self::deNulledByEveryCallerAndTravels($codebase, $finder),
        ));
    }

    /**
     * One task per candidate finder: each runs the (expensive) blast-radius check
     * for a single `?T` method, so the call-graph work spreads across the pool.
     */
    public function shards(Codebase $codebase): array
    {
        return array_map(
            static fn (NodeMatch $finder): Closure =>
                static fn (): array => self::deNulledByEveryCallerAndTravels($codebase, $finder) ? [$finder] : [],
            $this->candidates($codebase),
        );
    }

    /**
     * The cheap half: every method declaration that returns a nullable object —
     * the finders whose blast radius is then worth measuring.
     *
     * @return list<NodeMatch>
     */
    private function candidates(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->returnsNullableObject())
            ->get();
    }

    /**
     * The blast-radius check: the finder's result is de-nulled at every resolved
     * call site, and it reaches at least two of them (it travels).
     */
    private static function deNulledByEveryCallerAndTravels(Codebase $codebase, AstNode $finder): bool
    {
        $class = $finder->enclosingClassName();
        $method = $finder->enclosingFunctionName();

        if ($class === null || $method === null) {
            return false;
        }

        $callers = $codebase->index()->callersOf($class, $method);
        $deNulled = array_filter($callers, static fn (NodeMatch $caller): bool => $caller->resultIsDeNulled());

        return count($deNulled) >= self::TRAVELS && count($deNulled) === count($callers);
    }
}
