<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class AccessAuditor
{
    public const SEPARATOR = '/';

    #[Sinful(DataClump::class)]
    public function record(string $shopId, string $userId, string $channelId): string
    {
        return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
    }

    /**
     * The clump named: one value object carries the three fields that travelled
     * together.
     */
    #[Righteous(DataClump::class)]
    public function recordAccess(AccessContext $context): string
    {
        return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
    }
}
