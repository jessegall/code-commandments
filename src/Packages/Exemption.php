<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use InvalidArgumentException;
use JesseGall\CodeCommandments\Discovery;

/**
 * One kind of exemption — the TAG a detector reads and a package registers against ({@see
 * Exemptions}). A concrete exemption (under `Tags/`) names itself with a {@see slug} and explains
 * itself with a {@see description}, so it's referable by a short slug (a package developer needs
 * only know `'boundary'`, not the FQCN) and listable by the `exemptions` command.
 *
 * A tag is ALWAYS an `Exemption` — a custom one your detector reads must be its OWN subclass of
 * this (with a slug + description), never a random class; {@see resolve} enforces that.
 */
abstract class Exemption
{
    /** The short id — what a package may pass to `exempt(...)` instead of the class, and the `exemptions` key. */
    abstract public function slug(): string;

    /** One line: what this exemption means, and which rules honour it. */
    abstract public function description(): string;

    /**
     * Every built-in exemption class, discovered from `Tags/`.
     *
     * @return list<class-string<self>>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            Discovery::classes(__DIR__ . '/Tags', __NAMESPACE__ . '\\Tags'),
            static fn (string $class): bool => is_subclass_of($class, self::class),
        ));
    }

    /**
     * The exemption class-string for a slug OR a class-string. A subclass of this passes through; a
     * known {@see slug} resolves to its class. Anything else — a random class that is NOT an
     * `Exemption`, or an unknown slug — is not a valid tag and throws: a tag is ALWAYS an exemption.
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
