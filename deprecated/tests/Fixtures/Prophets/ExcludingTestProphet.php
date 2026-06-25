<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

class ExcludingTestProphet extends BaseCommandment
{
    public static bool $wasJudged = false;

    public function applicableExtensions(): array
    {
        return ['php'];
    }

    public function description(): string
    {
        return 'Test';
    }

    public function detailedDescription(): string
    {
        return 'Test prophet';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        static::$wasJudged = true;

        return $this->righteous();
    }

    public static function resetState(): void
    {
        static::$wasJudged = false;
    }
}
