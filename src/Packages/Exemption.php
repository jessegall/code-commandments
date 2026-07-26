<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use InvalidArgumentException;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Discovery;

/**
 * An exemption tag — what a detector reads and a package registers. Concrete exemptions under
 * `Tags/` name themselves with a {@see slug} and {@see description}, referable by short slug and
 * listable by the `exemptions` command. Custom detectors subclass this with their own tags.
 */
abstract class Exemption
{
    /**
     * The short id — what a package may pass to `exempt(...)` instead of the class, and the `exemptions` key.
     */
    abstract public function slug(): string;

    /**
     * One line: what this exemption means, and which rules honour it.
     */
    abstract public function description(): string;

    /**
     * Every known exemption: the built-in tags under `Tags/`, plus each tag a detector declares it
     * honours ({@see Exemptable}) — so a custom detector's own exemption is listable and its slug
     * resolvable, the same as a built-in.
     *
     * @return list<class-string<self>>
     */
    public static function all(): array
    {
        $builtin = array_filter(
            Discovery::classes(__DIR__ . '/Tags', __NAMESPACE__ . '\\Tags'),
            static fn (string $class): bool => is_subclass_of($class, self::class),
        );

        $declared = [];

        foreach (Catalog::all() as $detector) {
            if ($detector instanceof Exemptable) {
                array_push($declared, ...array_keys($detector->exemptions()));
            }
        }

        return array_values(array_unique([...$builtin, ...$declared]));
    }

    /**
     * Resolve a tag — an `Exemption` subclass or a {@see slug} — to its `Exemption` class. A
     * subclass passes through; a known slug maps to its class; anything else throws, since a tag
     * is always an exemption.
     *
     * @return class-string<self>
     */
    public static function resolve(string $tagOrSlug): string
    {
        if (is_subclass_of($tagOrSlug, self::class)) {
            return $tagOrSlug;
        }

        foreach (self::all() as $class) {
            if (new $class()->slug() === $tagOrSlug) {
                return $class;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '"%s" is not an exemption — pass an %s subclass or a known slug (%s).',
            $tagOrSlug,
            self::class,
            implode(', ', array_map(static fn (string $c): string => new $c()->slug(), self::all())),
        ));
    }
}
