<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Detector as RootDetector;

/**
 * The single source of truth for every detector that ships. Discovered from the
 * `Backend/` and `Frontend/` directories, so a detector counts the moment its file
 * exists — there is no second list to keep in sync, nothing to forget to register.
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

        foreach (glob(__DIR__ . "/{$engine}/*Detector.php") ?: [] as $file) {
            $class = __NAMESPACE__ . "\\{$engine}\\" . basename($file, '.php');

            if (is_subclass_of($class, RootDetector::class)) {
                $detectors[] = new $class;
            }
        }

        usort($detectors, static fn (RootDetector $a, RootDetector $b): int => $a::class <=> $b::class);

        return $detectors;
    }
}
