<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class AccessAuditor
{
    public const SEPARATOR = '/';

    #[Sinful(DataClumpDetector::class)]
    public function record(string $shopId, string $userId, string $channelId): string
    {
        return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
    }
}
