<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;

/**
 * The shared decision "is this method a markerless-registry leaky nullable
 * getter?" — the exact firing set for BOTH the RegistryReturnContract markerless
 * warning and the registry-scoped NoNullCoalesceToNull auto-fix. Keeping it in
 * one place is what lets the auto-fix stay strictly downstream of the cause: C4
 * only strips a `?? null` on a getter C2 also flags, so the repent guard (which
 * defers C4 to the unresolved C2) always covers it.
 *
 * Fires on a public, non-magic getter of a {@see RegistryShape} class that
 * returns a RAW `?T` (never an Option — that is a genuine-absence opt-in) and
 * reads the keyed store, when EITHER the name is not a finder, OR it is a finder
 * but every (transitive) caller de-nulls it (so the name's "absence is normal"
 * promise is contradicted by the call sites).
 */
final class RegistryLeak
{
    private const FINDER_PREFIXES = ['find', 'search', 'try', 'lookup'];

    public static function isLeakyNullableGetter(
        Node\Stmt\Class_ $class,
        RegistryShape $shape,
        Node\Stmt\ClassMethod $method,
        ?string $fqcn,
        ?CallConsumptionCensus $census,
    ): bool {
        if (! $method->isPublic() || $method->isStatic()) {
            return false;
        }

        $name = $method->name->toString();

        if (str_starts_with($name, '__')) {
            return false;
        }

        if (! self::isRawNullable($method) || ! $shape->readsStore($method)) {
            return false;
        }

        if (self::isFinderName($name)) {
            return $census !== null
                && $fqcn !== null
                && $census->allCallersDeNull($fqcn, $name, 'nullable');
        }

        return true;
    }

    /**
     * Whether the return type is a raw `?T` / `T | null` (NOT an `Option`).
     */
    public static function isRawNullable(Node\Stmt\ClassMethod $method): bool
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A getter name that ANNOUNCES nullability is normal (a finder) — its
     * "absence is expected" promise is only overridden by cross-file evidence.
     */
    public static function isFinderName(string $name): bool
    {
        $lower = strtolower($name);

        foreach (self::FINDER_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        if (str_ends_with($lower, 'ornull') || str_ends_with($lower, 'ordefault')) {
            return true;
        }

        // A `<thing>For<Other>` directional lookup (keyForClass, classForKey).
        return preg_match('/[a-z0-9]For[A-Z]/', $name) === 1;
    }
}
