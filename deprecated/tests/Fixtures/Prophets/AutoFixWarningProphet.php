<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;

/**
 * Test fixture: emits an [AUTO-FIXABLE] WARNING (never a sin) for the marker
 * `AUTOFIX_ME`, and repents it to `AUTOFIXED`. Used to prove repent acts on
 * auto-fixable warnings without a severity bump (#48).
 */
class AutoFixWarningProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Auto-fixable warning fixture';
    }

    public function detailedDescription(): string
    {
        return 'Replace AUTOFIX_ME with AUTOFIXED.';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if (! str_contains($content, 'AUTOFIX_ME')) {
            return $this->righteous();
        }

        return Judgment::withWarnings([
            $this->warningAt(1, 'AUTOFIX_ME should be AUTOFIXED', 'AUTOFIX_ME', 'autofix:marker', autoFixable: true),
        ]);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! str_contains($content, 'AUTOFIX_ME')) {
            return RepentanceResult::unchanged();
        }

        return RepentanceResult::absolved(
            str_replace('AUTOFIX_ME', 'AUTOFIXED', $content),
            ['Rewrote AUTOFIX_ME to AUTOFIXED'],
        );
    }
}
