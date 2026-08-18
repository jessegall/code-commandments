<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Discovery;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Unpublished;
use JesseGall\CodeCommandments\WholeTree;

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
        return self::named(self::all(), $name);
    }

    /**
     * The detector in $detectors whose SHORT class name matches $name, or null — the single home of
     * that leniency, so a project's OWN detectors ({@see \JesseGall\CodeCommandments\Custom}) resolve
     * a `--sin=`/`--detector=` name exactly as a shipped one does.
     *
     * @param  list<RootDetector>  $detectors
     */
    public static function named(array $detectors, string $name): ?RootDetector
    {
        $wanted = mb_strtolower(self::withoutSuffix($name));

        foreach ($detectors as $detector) {
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
     * The detectors that can judge ONE file honestly — everything except the {@see WholeTree} rules,
     * whose verdict rests on the call graph or the value-flow graph and so cannot be asked about a
     * file in isolation. Both engines: a `.vue` edit deserves the same check a `.php` one gets.
     *
     * This is for the fast per-edit check, never for `judge`, which parses everything and runs the lot.
     *
     * @return list<RootDetector>
     */
    public static function singleFile(): array
    {
        return array_values(array_filter(
            [...self::backend(), ...self::frontend()],
            static fn (RootDetector $detector): bool => ! $detector instanceof WholeTree,
        ));
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
