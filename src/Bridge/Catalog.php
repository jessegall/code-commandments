<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

use JesseGall\CodeCommandments\Discovery;

/**
 * The single source of truth for every {@see ContractProvider} that ships — the
 * PUBLISH side of the {@see Bridge}, discovered from the `Backend/` and `Frontend/`
 * trees exactly as {@see \JesseGall\CodeCommandments\Detectors\Catalog} discovers
 * detectors. A provider counts the moment its file exists; the engine interfaces in
 * those folders are skipped ({@see \class_exists} is false for an interface).
 */
final class Catalog
{
    /**
     * The backend providers — run over an {@see \JesseGall\CodeCommandments\Ast\Codebase}.
     *
     * @return list<Backend\ContractProvider>
     */
    public static function backend(): array
    {
        return self::discover('Backend');
    }

    /**
     * The frontend providers — run over a {@see \JesseGall\CodeCommandments\Vue\Codebase}.
     *
     * @return list<Frontend\ContractProvider>
     */
    public static function frontend(): array
    {
        return self::discover('Frontend');
    }

    /**
     * @return list<ContractProvider>
     */
    private static function discover(string $engine): array
    {
        $providers = [];

        foreach (Discovery::classes(__DIR__ . "/{$engine}", __NAMESPACE__ . "\\{$engine}", 'Provider') as $class) {
            if (class_exists($class) && is_subclass_of($class, ContractProvider::class)) {
                $providers[] = new $class;
            }
        }

        usort($providers, static fn (ContractProvider $a, ContractProvider $b): int => $a::class <=> $b::class);

        return $providers;
    }
}
