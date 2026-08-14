<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * Reads a class name out of a type declaration. Names are resolved at parse time,
 * so the returned name is fully qualified. Builtins (scalars, array, void, self…)
 * are not class names and yield null.
 */
final class TypeName
{
    private const array BUILTINS = [
        'array', 'string', 'int', 'float', 'bool', 'mixed', 'object', 'void',
        'never', 'iterable', 'callable', 'self', 'static', 'parent', 'true',
        'false', 'null',
    ];

    /**
     * The single class FQCN of a (possibly nullable) class type — `C`, `?C`, or
     * `C | null` — else null (a scalar, array, void, or a multi-class union).
     */
    public static function class(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::class($type->type);
        }

        if ($type instanceof Name) {
            $name = $type->toString();

            return self::isClassName($name) ? $name : null;
        }

        if ($type instanceof UnionType) {
            return self::singleClassOfUnion($type);
        }

        return null;
    }

    /**
     * Does this written type name a CLASS rather than a builtin (`string`, `array`, `self`, …)? The
     * string-level twin of {@see class}, for a caller holding a resolved type name instead of the type
     * node — so "is this a scalar or an object" is answered in one place either way.
     */
    public static function isClassName(?string $name): bool
    {
        return $name !== null && ! in_array(strtolower(ltrim($name, '?\\')), self::BUILTINS, true);
    }

    /**
     * The plain written name of a single type — a class `Name` OR a builtin `Identifier` (`int`,
     * `string`) — with a `?T` wrapper stripped, else null (a union, intersection, or absent type). Unlike
     * {@see class}, this KEEPS builtins: the caller wants the name as written, not just class types.
     */
    public static function simpleName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        // `T | null` written as a union (not the `?T` sugar) — strip the null arm and read the lone remainder.
        if ($type instanceof UnionType) {
            $type = self::soleNonNullMember($type);
        }

        return match (true) {
            $type instanceof Name => $type->toString(),
            $type instanceof Identifier => $type->toString(),
            default => null,
        };
    }

    /**
     * The single non-`null` member of a union, or null when it has zero or more than one — so `string|null`
     * yields the `string` node but `int|string` (a genuine multi-type) yields null.
     */
    private static function soleNonNullMember(UnionType $type): ?Node
    {
        $members = array_values(array_filter(
            $type->types,
            static fn (Node $member): bool => ! self::isNullMember($member),
        ));

        return count($members) === 1 ? $members[0] : null;
    }

    /**
     * The class FQCN when the type is NULLABLE and resolves to one class — `?C`
     * or `C | null` — else null. Used to spot a nullable object return.
     */
    public static function nullableClass(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::class($type->type);
        }

        if (self::isNullableUnion($type)) {
            return self::singleClassOfUnion($type);
        }

        return null;
    }

    /**
     * Is the type nullable at all — `?X` or a union containing `null`?
     */
    public static function isNullable(?Node $type): bool
    {
        return $type instanceof NullableType || (self::isNullableUnion($type));
    }

    /**
     * Is the type a nullable `array` — `?array` or `array | null`?
     */
    public static function isNullableArray(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return $type->type instanceof Identifier && $type->type->toString() === 'array';
        }

        if (self::isNullableUnion($type)) {
            foreach ($type->types as $member) {
                if ($member instanceof Identifier && $member->toString() === 'array') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A normalized, comparable string for a type declaration — `?Foo`, `int`, `A|B` (union members sorted so
     * spelling order doesn't matter). The reusable "are these two types the same" primitive; null renders `''`.
     */
    public static function render(?Node $type): string
    {
        if ($type instanceof NullableType) {
            return '?' . self::render($type->type);
        }

        if ($type instanceof Name || $type instanceof Identifier) {
            return ltrim($type->toString(), '\\');
        }

        if ($type instanceof UnionType) {
            $parts = array_map(static fn (Node $member) => self::render($member), $type->types);
            sort($parts);

            return implode('|', $parts);
        }

        return $type === null ? '' : $type::class;
    }

    /**
     * Does a UNION type list $fqcn as one of its members — e.g. `Foo | Optional` includes `…\Optional`?
     * A reusable check for a marker type unioned onto a real type (the Spatie `Optional` shape, a sentinel).
     */
    public static function unionIncludes(?Node $type, string $fqcn): bool
    {
        if (! $type instanceof UnionType) {
            return false;
        }

        foreach ($type->types as $member) {
            if ($member instanceof Name && ltrim($member->toString(), '\\') === ltrim($fqcn, '\\')) {
                return true;
            }
        }

        return false;
    }

    private static function singleClassOfUnion(UnionType $type): ?string
    {
        $classes = [];

        foreach ($type->types as $member) {
            if (self::isNullMember($member)) {
                continue;
            }

            $class = self::class($member instanceof Node ? $member : null);

            if ($class === null) {
                return null;
            }

            $classes[] = $class;
        }

        return count($classes) === 1 ? $classes[0] : null;
    }

    /**
     * Is $member the `null` of a union — `T|null`, whatever its case, and whether the parser handed
     * it over as an Identifier or a Name (both spellings reach here depending on context).
     */
    public static function isNullMember(Node $member): bool
    {
        return ($member instanceof Identifier || $member instanceof Name)
            && strtolower($member->toString()) === 'null';
    }

    /**
     * Is $type a union that includes `null` — the `T|null` half of "nullable"?
     */
    public static function isNullableUnion(?Node $type): bool
    {
        return $type instanceof UnionType && self::unionHasNull($type);
    }

    private static function unionHasNull(UnionType $type): bool
    {
        foreach ($type->types as $member) {
            if (self::isNullMember($member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this PARAMETER promise a present value of the named scalar type — declared exactly `string`
     * (or `int`, …), not nullable, and with no default to fall back on? The primitive behind "the slot
     * says a real value is always there", so a caller filling it with nothing is a lie the types hide.
     */
    public static function promisesScalar(?Param $param, string $scalar): bool
    {
        return $param !== null
            && $param->default === null
            && $param->type instanceof Identifier
            && $param->type->toString() === $scalar;
    }
}
