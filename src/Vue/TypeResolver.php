<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Resolves a TYPE NAME to its shape across the module graph — the "trace to the API". A
 * component references `WizardSnapshotData`; this follows the trail a bundler/type-checker
 * would: declared here? done. Imported (`import type { X } from '@app/types'`)? resolve the
 * module and look there. A barrel that only `export *`s onward? follow each re-export until
 * the real `export type X = { … }` is found — typically the generated server-Data file.
 *
 * Pure graph walk over {@see ModuleResolver} (relative/alias/barrel) + {@see Script} (the
 * type/import/re-export readers), with a visited-set so a cyclic barrel can't loop. Returns
 * the type's fields, or `[]` when the trail runs cold (an enum union, a node_modules type,
 * an unresolved alias) — never a guess.
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

    private static function scriptOf(string $path): Script
    {
        $source = (string) file_get_contents($path);

        return new Script(str_ends_with($path, '.vue')
            ? Codebase::fromString($source, $path)->components()[0]->scriptContent()
            : $source);
    }
}
