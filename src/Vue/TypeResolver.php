<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Resolves type names to their shapes across the module graph, following imports and re-exports;
 * returns fields or [] when the trail runs cold.
 */
final class TypeResolver
{
    /**
     * The fields of $type as referenced from $script (the file at $file), following imports
     * and re-exports to wherever it is declared.
     *
     * @return array<string, string>
     */
    public static function fields(string $type, string $file, Script $script): array
    {
        return self::resolve($type, $file, $script, []);
    }

    /**
     * @param  list<string>  $seen  files already visited (cycle guard)
     * @return array<string, string>
     */
    private static function resolve(string $type, string $file, Script $script, array $seen): array
    {
        if (in_array($file, $seen, true)) {
            return [];
        }

        $seen[] = $file;

        $local = $script->typeFields($type);

        if ($local !== []) {
            return $local; // declared right here
        }

        // Where it might live: the module it's imported from, then any barrel re-export.
        $imported = $script->importSpecifier($type);
        $specifiers = $imported === null ? $script->reExports() : [$imported, ...$script->reExports()];

        foreach ($specifiers as $specifier) {
            $path = ModuleResolver::forFile($file)->resolve($file, $specifier);

            if ($path === null) {
                continue;
            }

            $fields = self::resolve($type, $path, self::scriptOf($path), $seen);

            if ($fields !== []) {
                return $fields;
            }
        }

        return [];
    }

    public static function scriptOf(string $path): Script
    {
        $source = (string) file_get_contents($path);

        return new Script(str_ends_with($path, '.vue')
            ? Codebase::fromString($source, $path)->components()[0]->scriptContent()
            : $source);
    }
}
