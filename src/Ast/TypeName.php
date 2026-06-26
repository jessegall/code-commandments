<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\Identifier;
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

            return in_array(strtolower($name), self::BUILTINS, true) ? null : $name;
        }

        if ($type instanceof UnionType) {
            return self::singleClassOfUnion($type);
        }

        return null;
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

        if ($type instanceof UnionType && self::unionHasNull($type)) {
            return self::singleClassOfUnion($type);
        }

        return null;
    }

    /**
     * Is the type a nullable `array` — `?array` or `array | null`?
     */
    public static function isNullableArray(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return $type->type instanceof Identifier && $type->type->toString() === 'array';
        }

        if ($type instanceof UnionType && self::unionHasNull($type)) {
            foreach ($type->types as $member) {
                if ($member instanceof Identifier && $member->toString() === 'array') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function singleClassOfUnion(UnionType $type): ?string
    {
        $classes = [];

        foreach ($type->types as $member) {
            if ($member instanceof Identifier && strtolower($member->toString()) === 'null') {
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

    private static function unionHasNull(UnionType $type): bool
    {
        foreach ($type->types as $member) {
            if ($member instanceof Identifier && strtolower($member->toString()) === 'null') {
                return true;
            }

            if ($member instanceof Name && strtolower($member->toString()) === 'null') {
                return true;
            }
        }

        return false;
    }
}
