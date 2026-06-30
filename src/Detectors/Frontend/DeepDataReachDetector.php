<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;

/**
 * In a sizeable template, an element that reaches DEEP into nested data — a binding
 * or interpolation like `data.user.firstName` (two+ property hops past the root).
 * The element knows the whole data shape; that's Law of Demeter in the markup, and a
 * sign it wants to be its own component taking the mid-object as a prop (so it
 * reaches `user.firstName`, not `data.user.firstName`). Points at vue-components.
 *
 * Depth is read off the parsed JS expression AST via the engine's `reachesAtLeast`
 * filter, not a regex — so a method call (`order.customer.greet()`), a ref unwrap
 * and a dotted string literal (`route('a.b.c')`) are understood structurally, not
 * pattern-matched. Gated on size: a deep reach in a tiny component isn't worth a file.
 */
final class DeepDataReachDetector implements Detector
{
    private const int MIN_TEMPLATE_LINES = 50;

    private const int MIN_DEPTH = 2; // property hops past the root: data.user.firstName

    /** Accessors that read reactive state / a count, not a nested data shape. */
    private const array TRANSPARENT = ['value', 'length'];

    public function skill(): string
    {
        return 'vue-components';
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->inTemplateOfAtLeast(self::MIN_TEMPLATE_LINES)
            ->reachesAtLeast(self::MIN_DEPTH, self::TRANSPARENT)
            ->get();
    }
}
