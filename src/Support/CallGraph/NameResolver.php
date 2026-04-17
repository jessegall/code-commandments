<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;

/**
 * AST-only FQCN resolution.
 *
 * Given a type name as it appears in source plus the file's use-statement
 * map and namespace, return the fully qualified class name. Used by both
 * the prophet pipeline and the cross-file CodebaseIndex so resolution
 * semantics stay identical.
 */
final class NameResolver
{
    /**
     * @param  array<string, string>  $useStatements  short alias => FQCN
     */
    public static function resolve(string $typeName, array $useStatements, ?string $namespace): string
    {
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $useStatements[$firstPart];
            }

            $parts[0] = $useStatements[$firstPart];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $typeName;
        }

        return $typeName;
    }

    /**
     * Extract a string type name from an AST type node, collapsing nullable
     * and union/intersection wrappers to the first concrete name.
     */
    public static function typeName(?Node $type): ?string
    {
        if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return self::typeName($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return self::typeName($type->types[0] ?? null);
        }

        return null;
    }
}
