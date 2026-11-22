<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Groups sale days by the month they fall in, the month worked out by a private helper used as the key.
 */
#[Righteous(UnusedPrivateMember::class)]
final class PeriodBuckets
{
    /**
     * @param  list<int>  $days
     * @return array<string, list<int>>
     */
    public function bucket(array $days): array
    {
        $buckets = [];
        foreach ($days as $day) {
            $buckets[$this->monthOf($day)][] = $day;
        }

        return $buckets;
    }

    private function monthOf(int $day): string
    {
        return (string) intdiv($day, 30);
    }
}

/**
 * Sorts sale rows by their time through a private comparison named as a string callable.
 */
#[Righteous(UnusedPrivateMember::class)]
final class SaleTimeSorter
{
    /**
     * @param  list<SaleTime>  $sales
     * @return list<SaleTime>
     */
    public function sort(array $sales): array
    {
        usort($sales, 'self::byTime');

        return $sales;
    }

    private static function byTime(SaleTime $first, SaleTime $second): int
    {
        return $first->minutes <=> $second->minutes;
    }
}

/**
 * When in the day a sale was rung up.
 */
final class SaleTime
{
    public function __construct(public readonly int $minutes) {}
}
