<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * The per-codebase memo shared by every cross-cutting analysis that is built once per {@see Codebase} and
 * reused — {@see TypeResolver}, {@see DataClassShape}, {@see ResponseSurface}, {@see RouteActions},
 * {@see \JesseGall\CodeCommandments\Ast\Spatie\DataConstructions}. The `WeakMap` is per-using-class (a trait
 * copies its properties), so each analysis keeps its own cache and a codebase that is garbage-collected
 * drops every memo with it.
 *
 * The default {@see build} simply constructs `new static($codebase)`; an analysis that must gather or
 * pre-process first defines its OWN `build` (a class method silently overrides the trait's), so both shapes
 * share the one `forCodebase` entry point.
 */
trait MemoisedPerCodebase
{
    private static ?\WeakMap $memo = null;

    public static function forCodebase(Codebase $codebase): static
    {
        self::$memo ??= new \WeakMap();

        return self::$memo[$codebase] ??= static::build($codebase);
    }

    protected static function build(Codebase $codebase): static
    {
        return new static($codebase);
    }
}
