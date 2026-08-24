<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * Which units of a reading have enough in common to be worth comparing at all — the co-occurrence
 * pairing every reach-based rule needs, done once. Pairs are found THROUGH the resources themselves
 * rather than by comparing every unit with every other, so two units that share nothing are never
 * considered, and a pair is offered once however many resources it shares.
 */
final class ReachPairs
{
    /**
     * Every pair of $units sharing at least $minimum resources, the most alike first — so the clearest
     * mechanism seeds its group before a weaker one can claim a member.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return list<array{string, string}>
     */
    public static function sharing(array $units, int $minimum): array
    {
        $shared = self::counted($units);
        arsort($shared);

        $pairs = [];

        foreach ($shared as $pair => $count) {
            if ($count >= $minimum) {
                $pairs[] = self::split((string) $pair);
            }
        }

        return $pairs;
    }

    /**
     * How many resources each pair of units holds in common.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return array<string, int>
     */
    private static function counted(array $units): array
    {
        $holders = [];

        foreach ($units as $key => $unit) {
            foreach ($unit->resources as $resource) {
                $holders[$resource][] = $key;
            }
        }

        $shared = [];

        foreach ($holders as $keys) {
            foreach ($keys as $index => $one) {
                foreach (array_slice($keys, $index + 1) as $other) {
                    $pair = self::join($one, $other);
                    $shared[$pair] = ($shared[$pair] ?? 0) + 1;
                }
            }
        }

        return $shared;
    }

    /**
     * One key for a pair whichever order it was met in, so it is counted once rather than twice.
     */
    private static function join(string $one, string $other): string
    {
        return $one < $other ? "{$one}\0{$other}" : "{$other}\0{$one}";
    }

    /**
     * @return array{string, string}
     */
    private static function split(string $pair): array
    {
        [$one, $other] = explode("\0", $pair);

        return [$one, $other];
    }
}
