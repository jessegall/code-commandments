<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node\Stmt\Enum_;

/**
 * Reaching into the container with `app()` / `resolve()` from a class the
 * container itself resolves — the dependency belongs in the constructor.
 * Points at laravel-idioms.
 *
 * Suppressed where the container can't build the class, so app()/resolve() is
 * the only option: an enum (never constructible), or a class only ever
 * instantiated by hand (its constructor isn't filled by the container).
 */
final class ContainerReachDetector implements Detector
{
    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('app', 'resolve')
            ->where(function (NodeMatch $match) use ($codebase): bool {
                $class = $match->enclosingClassName();

                if ($class === null || $match->enclosingClass() instanceof Enum_) {
                    return false;
                }

                return $this->isContainerResolved($codebase, $class);
            })
            ->get();
    }

    private function isContainerResolved(Codebase $codebase, string $class): bool
    {
        // Injected as a constructor dependency somewhere → the container builds it.
        $injected = $codebase->whereParamType($class)
            ->where(static fn (NodeMatch $match): bool => $match->enclosingFunctionName() === '__construct')
            ->count() > 0;

        // ...or never instantiated by hand, so the container is its only source.
        $neverNewed = $codebase->whereNew($class)->count() === 0;

        return $injected || $neverNewed;
    }
}
