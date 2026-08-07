<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The last segment of a fully-qualified class name — `App\Data\OrderData` becomes `OrderData`. The one home
 * for a computation a dozen places used to each keep a private copy of; pass `$object::class` for an
 * instance. Pure string work, no reflection, so it is safe for any layer to call.
 */
final class ClassName
{
    public static function short(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    /**
     * The FIRST namespace segment of a fully-qualified name — `App\Data\OrderData` → `App`. The vendor root
     * a cross-reference is judged first-party against.
     */
    public static function root(?string $fqcn): string
    {
        // A class that cannot be named has no root namespace — `''`, which is what every caller
        // then tests for, rather than each manufacturing the empty name to ask about.
        return $fqcn === null ? '' : explode('\\', ltrim($fqcn, '\\'))[0];
    }

    /**
     * Everything BEFORE the last segment — `App\Data\OrderData` → `App\Data`; `''` for a name at global
     * scope. The namespace a class lives in, which is what a layer/boundary rule judges it by.
     */
    public static function namespace(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));

        array_pop($parts);

        return implode('\\', $parts);
    }

    /**
     * Does $fqcn live inside $namespace — the same namespace, or one nested under it? Compared on
     * SEGMENT boundaries, so `App\Ui\Elements` is within `App\Ui` but `App\UiKit` is not (a bare
     * `str_starts_with` would wrongly say it is). Case-insensitive, as PHP namespaces are.
     */
    public static function within(string $fqcn, string $namespace): bool
    {
        $name = strtolower(ltrim($fqcn, '\\'));
        $within = strtolower(trim($namespace, '\\'));

        return $within === '' || $name === $within || str_starts_with($name, $within . '\\');
    }
}
