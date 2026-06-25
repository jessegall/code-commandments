<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;

/**
 * Test fixture for the profiles suite: flags the marker `SIN_ME` as a sin and
 * `WARN_ME` as a warning, so a judge run can assert whether warnings are emitted
 * (phased/grind) or suppressed (sins-only) and whether a warning-only file blocks.
 */
class ProfileMarkerProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Flags SIN_ME (sin) and WARN_ME (warning).';
    }

    public function detailedDescription(): string
    {
        return 'Test prophet: SIN_ME => sin, WARN_ME => warning.';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $sins = [];
        $warnings = [];

        if (str_contains($content, 'SIN_ME')) {
            $sins[] = new Sin('SIN_ME found', 1, null, 'SIN_ME', null, 'profile:sin');
        }

        if (str_contains($content, 'WARN_ME')) {
            $warnings[] = new Warning('WARN_ME found', 1, 'WARN_ME', 'profile:warn');
        }

        return new Judgment(sins: $sins, warnings: $warnings);
    }
}
