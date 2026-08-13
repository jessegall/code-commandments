<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use ReflectionClass;

/**
 * Finds the detectors a name means — by sin id or by detector class name, punctuation and case
 * ignored, so `array-bag`, `ArrayBag` and `ArrayBagDetector` all land on the same rule. One place,
 * because a name that resolves under `disable` but not under `info` reads as the tool being broken.
 */
final class SinLookup
{
    /**
     * @return list<Detector>
     */
    public static function detectors(string $query): array
    {
        $needle = self::normalise($query);

        return array_values(array_filter(
            self::every(),
            static fn (Detector $detector): bool => $detector->sin()->matches($query)
                || str_contains(self::normalise(new ReflectionClass($detector)->getShortName()), $needle),
        ));
    }

    /**
     * The names a user could have meant — what a failed lookup offers instead of nothing.
     *
     * @return list<string>
     */
    public static function suggestions(int $limit = 8): array
    {
        $names = array_map(static fn (Detector $detector): string => $detector->sin()->name(), self::every());
        sort($names);

        return array_slice(array_values(array_unique($names)), 0, $limit);
    }

    /**
     * Every rule a name could mean — the shipped catalogue AND the project's own (#469). A custom
     * rule fires with a name the reader is told to look up, so a lookup that searched only what we
     * ship answered "no rule matches" for the very rules most likely to be unfamiliar. Disabled
     * rules stay findable: explaining one is how a reader decides whether to turn it back on.
     *
     * @return list<Detector>
     */
    private static function every(): array
    {
        return [...Catalog::all(), ...Custom::detectors(ConsumerRoot::from(getcwd() ?: '.'))];
    }

    private static function normalise(string $text): string
    {
        return strtolower(str_replace(['-', '_', ' ', '\\'], '', $text));
    }
}
