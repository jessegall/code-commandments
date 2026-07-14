<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Discovery;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Unpublished;

/**
 * The single source of truth for every detector that ships. Discovered from the
 * `Backend/` and `Frontend/` trees (recursively, so a package's `Backend/Laravel/`
 * subfolder counts too), so a detector counts the moment its file exists — there is
 * no second list to keep in sync, nothing to forget to register.
 */
final class Catalog
{
    /**
     * Every detector, both engines — backend then frontend.
     *
     * @return list<Detector>
     */
    public static function all(): array
    {
        return [...self::backend(), ...self::frontend()];
    }

    /**
     * The published detector whose SHORT class name matches `$name` (the name a finding and a
     * `--detector=` report carry), or null if none does. Lenient on the `Detector` suffix, so both
     * `AllNullableDataDetector` and `AllNullableData` resolve.
     */
    public static function detectorNamed(string $name): ?RootDetector
    {
        $wanted = mb_strtolower(self::withoutSuffix($name));

        foreach (self::all() as $detector) {
            if (mb_strtolower(self::withoutSuffix(ClassName::short($detector::class))) === $wanted) {
                return $detector;
            }
        }

        return null;
    }

    private static function withoutSuffix(string $name): string
    {
        return str_ends_with($name, 'Detector') ? substr($name, 0, -strlen('Detector')) : $name;
    }

    /**
     * The backend (PHP AST) detectors — run over an {@see \JesseGall\CodeCommandments\Ast\Codebase}.
     *
     * @return list<Detector>
     */
    public static function backend(): array
    {
        return self::discover('Backend');
    }

    /**
     * The frontend (Vue) detectors — run over a {@see \JesseGall\CodeCommandments\Vue\Codebase}.
     *
     * @return list<Detector>
     */
    public static function frontend(): array
    {
        return self::discover('Frontend');
    }

    /**
     * @return list<Detector>
     */
    private static function discover(string $engine): array
    {
        $detectors = [];

        foreach (Discovery::classes(__DIR__ . "/{$engine}", __NAMESPACE__ . "\\{$engine}", 'Detector') as $class) {
            if (is_subclass_of($class, RootDetector::class) && ! is_subclass_of($class, Unpublished::class)) {
                $detectors[] = new $class;
            }
        }

        usort($detectors, static fn (RootDetector $a, RootDetector $b): int => $a::class <=> $b::class);

        return $detectors;
    }
}
