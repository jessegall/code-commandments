<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Test fixture: counts how many times judge() actually runs (so a cache HIT can
 * be proven by the counter NOT advancing), and flags the marker `FLAG_ME`.
 */
class CountingProphet extends PhpCommandment
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function description(): string
    {
        return 'Counts judge() calls';
    }

    public function detailedDescription(): string
    {
        return 'Counts judge() calls; flags FLAG_ME.';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        self::$calls++;

        if (! str_contains($content, 'FLAG_ME')) {
            return $this->righteous();
        }

        return Judgment::withWarnings([
            $this->warningAt(1, 'FLAG_ME found', 'FLAG_ME', 'counting:marker'),
        ]);
    }
}
