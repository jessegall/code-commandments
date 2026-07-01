<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

use JesseGall\CodeCommandments\Discovery;

/**
 * Every sin that ships, discovered from the `Backend/` and `Frontend/` folders — the
 * sin twin of {@see \JesseGall\CodeCommandments\Detectors\Catalog}. A sin counts the
 * moment its file exists; a consumer's own `Sins/` class auto-enrols the same way. The
 * generated `SKILL.md` "when it fires" rows are projected from this registry.
 */
final class Catalog
{
    /**
     * The backend (PHP) sins.
     *
     * @return list<Sin>
     */
    public static function all(): array
    {
        return self::discover('Backend');
    }

    /**
     * The frontend (Vue) sins.
     *
     * @return list<Sin>
     */
    public static function frontend(): array
    {
        return self::discover('Frontend');
    }

    /**
     * Every sin, both engines.
     *
     * @return list<Sin>
     */
    public static function every(): array
    {
        return [...self::all(), ...self::frontend()];
    }

    /**
     * @return list<Sin>
     */
    private static function discover(string $engine): array
    {
        $sins = [];

        foreach (Discovery::classes(__DIR__ . "/{$engine}", __NAMESPACE__ . "\\{$engine}") as $class) {
            if (is_subclass_of($class, Sin::class)) {
                $sins[] = new $class;
            }
        }

        usort($sins, static fn (Sin $a, Sin $b): int => $a::class <=> $b::class);

        return $sins;
    }
}
