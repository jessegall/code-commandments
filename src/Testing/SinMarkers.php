<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * Reads `#[Sinful]` markers off a parsed fixture — the spec the detector tests
 * check themselves against. Anything not marked is, by definition, clean.
 */
final class SinMarkers
{
    /**
     * @return list<Marker>
     */
    public static function in(Codebase $codebase): array
    {
        $markers = [];

        foreach ($codebase->whereAttribute('Sinful')->get() as $match) {
            $detector = self::detector($match);

            if ($detector === null) {
                continue;
            }

            $markers[] = new Marker(
                detector: $detector,
                class: $match->enclosingClassName() ?? '(file)',
                method: $match->enclosingFunctionName(),
                location: $match->location(),
            );
        }

        return $markers;
    }

    private static function detector(NodeMatch $match): ?string
    {
        $args = $match->arguments();

        if ($args === []) {
            return null;
        }

        $value = $args[0]->value;

        if ($value instanceof ClassConstFetch && $value->class instanceof Name) {
            return $value->class->toString();
        }

        if ($value instanceof String_) {
            return $value->value;
        }

        return null;
    }
}
