<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

class SupportedTestProphet extends BaseCommandment
{
    public bool $wasJudged = false;

    public function supported(): bool
    {
        return true;
    }

    public function applicableExtensions(): array
    {
        return ['php'];
    }

    public function description(): string
    {
        return 'Supported';
    }

    public function detailedDescription(): string
    {
        return 'Supported prophet';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $this->wasJudged = true;

        return $this->righteous();
    }
}
