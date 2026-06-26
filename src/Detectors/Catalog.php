<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

/**
 * The single source of truth for every detector that ships. Discovered from the
 * `Backend/` directory, so a detector counts the moment its file exists — there
 * is no second list to keep in sync, nothing to forget to register.
 */
final class Catalog
{
    /**
     * @return list<Detector>
     */
    public static function all(): array
    {
        $detectors = [];

        foreach (glob(__DIR__ . '/Backend/*Detector.php') ?: [] as $file) {
            $class = __NAMESPACE__ . '\\Backend\\' . basename($file, '.php');

            if (is_subclass_of($class, Detector::class)) {
                $detectors[] = new $class;
            }
        }

        usort($detectors, static fn (Detector $a, Detector $b): int => $a::class <=> $b::class);

        return $detectors;
    }
}
