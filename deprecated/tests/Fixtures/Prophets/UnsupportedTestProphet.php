<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Prophets;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

class UnsupportedTestProphet extends BaseCommandment
{
    public function supported(): bool
    {
        return false;
    }

    public function applicableExtensions(): array
    {
        return ['php'];
    }

    public function description(): string
    {
        return 'Unsupported';
    }

    public function detailedDescription(): string
    {
        return 'Unsupported prophet';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return $this->righteous();
    }
}
