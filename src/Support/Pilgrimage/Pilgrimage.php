<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Fingerprint;
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
     * When $onlyProphet is given (a single-prophet walk — repentr, or any
     * `pilgrimage <PROPHET>`), the itinerary collapses to ONE station holding just
     * that prophet, named after its home doctrine. Everything downstream reads "the
     * current prophet" through the cursor, so a one-station itinerary makes the whole
     * walk single-prophet for free.
     *
     * @param  list<class-string<Commandment>>  $registered  the scroll's full prophet set
     * @param  class-string<Commandment>|null  $onlyProphet  constrain to one prophet
     * @return list<array{name: string, pillars: list<list<class-string<Commandment>>>}>
     */
    public static function itinerary(array $registered, ?string $onlyProphet = null): array
    {
        if ($onlyProphet !== null) {
            $located = DoctrineRegistry::locate($onlyProphet);

            return [['name' => $located['doctrine'] ?? 'singletons', 'pillars' => [[$onlyProphet]]]];
        }

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
     * at (severity already applied; a disabled prophet returns nothing). Findings the
     * consumer has ABSOLVED or REPORTED are skipped — they must not block the walk
     * (#213); the fingerprint matches the same content-based key `absolve`/`report`
     * record, so it self-heals when the code changes.
     *
     * When $allowWarnings is false (the sins-only profile), warnings are dropped
     * AFTER `applyConfiguredSeverity()` — so a warning a prophet's config promotes to
     * a sin still surfaces, and a prophet with only admonitions yields nothing (it is
     * skipped clean, never a station). The post-severity order is load-bearing.
     *
     * @param  list<string>  $files
     * @return list<array{file: string, line: int|null, message: string, autoFixable: bool}>
     */
    public function scanProphet(Commandment $prophet, array $files, CodebaseIndex $index, string $basePath, ?ConfessionTracker $tracker = null, bool $allowWarnings = true): array
    {
        if ($prophet instanceof NeedsCodebaseIndex) {
            $prophet->setCodebaseIndex($index);
        }

        $prophetClass = $prophet::class;
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

            $relative = $this->relative($file, $basePath);

            $findings = $allowWarnings ? [...$judgment->sins, ...$judgment->warnings] : $judgment->sins;

            foreach ($findings as $finding) {
                if ($tracker !== null && $this->isSilenced($tracker, $prophetClass, $relative, $finding)) {
                    continue;
                }

                $locations[] = [
                    'file' => $relative,
                    'line' => $finding->line,
                    'message' => $finding->message,
                    'autoFixable' => $finding->autoFixable ?? false,
                ];
            }
        }

        return $locations;
    }

    private function isSilenced(ConfessionTracker $tracker, string $prophetClass, string $relativePath, Sin|Warning $finding): bool
    {
        $fingerprint = Fingerprint::of($prophetClass, $relativePath, $finding->symbol, $finding->snippet);

        return $tracker->isFindingAbsolved($fingerprint) || $tracker->isFindingReported($fingerprint);
    }

    private function relative(string $file, string $basePath): string
    {
        $base = rtrim($basePath, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
