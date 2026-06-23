<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Test double that exempts a single configured primitive FQCN, so we can prove
 * a file declaring that exact class is skipped while a same-short-name domain
 * class is still judged.
 */
class ExemptingTestProphet extends BaseCommandment
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
        return 'Test prophet that exempts its own primitive';
    }

    /** @var list<class-string|string> */
    public static array $exempt = ['App\\Support\\Option'];

    public function exemptClasses(): array
    {
        return static::$exempt;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        static::$wasJudged = true;

        return $this->righteous();
    }

    public static function resetState(): void
    {
        static::$wasJudged = false;
        static::$exempt = ['App\\Support\\Option'];
    }
}
