<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Doctrines\DoctrineRegistry;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

/**
 * The itinerary + per-PROPHET scan behind `pilgrimage` / `next`. The walk visits
 * each doctrine in registry order, pillar by pillar (coarse → fine), and within a
 * pillar one prophet at a time; the homeless singletons become one final doctrine.
 * Only the current prophet is ever dispatched at a step (one rule, not all 91), so
 * each step is cheap — and because a prophet is reached only after the coarser
 * pillars are clean, the cascade (symptoms vanish once their root is fixed) is
 * emergent, no region-suppression engine required.
 */
final class Pilgrimage
{
    /**
     * The ordered walk: every doctrine's pillars, then a final `singletons`
     * doctrine holding the prophets that belong to no doctrine.
     *
     * @param  list<class-string<Commandment>>  $registered  the scroll's full prophet set
     * @return list<array{name: string, pillars: list<list<class-string<Commandment>>>}>
     */
    public static function itinerary(array $registered): array
    {
        $stations = [];

        foreach (DoctrineRegistry::all() as $doctrine) {
            $stations[] = ['name' => $doctrine->name, 'pillars' => $doctrine->bands];
        }

        $homeless = array_values(array_filter(
            $registered,
            static fn (string $class): bool => DoctrineRegistry::locate($class) === null,
        ));

        if ($homeless !== []) {
            // One pillar, each singleton its own step — they fire alone, no order.
            $stations[] = ['name' => 'singletons', 'pillars' => array_map(static fn (string $c): array => [$c], $homeless)];
        }

        return $stations;
    }

    /**
     * Dispatch ONE prophet over the frozen scope and return every location it fires
     * at (severity already applied; a disabled prophet returns nothing).
     *
     * @param  list<string>  $files
     * @return list<array{file: string, line: int|null, message: string}>
     */
    public function scanProphet(Commandment $prophet, array $files, CodebaseIndex $index, string $basePath): array
    {
        if ($prophet instanceof NeedsCodebaseIndex) {
            $prophet->setCodebaseIndex($index);
        }

        $locations = [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $judgment = $prophet->judge($file, $content);

            if ($prophet instanceof BaseCommandment) {
                $judgment = $prophet->applyConfiguredSeverity($judgment);
            }

            foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
                $locations[] = [
                    'file' => $this->relative($file, $basePath),
                    'line' => $finding->line,
                    'message' => $finding->message,
                ];
            }
        }

        return $locations;
    }

    private function relative(string $file, string $basePath): string
    {
        $base = rtrim($basePath, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
