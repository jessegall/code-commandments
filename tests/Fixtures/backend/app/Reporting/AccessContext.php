<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * The three ids that always travelled together, named once. Signatures shrink to one parameter and
 * nothing can pass them in the wrong order any more.
 */
#[Fixed(DataClump::class)]
final class AccessContext
{
    public function __construct(
        public readonly string $shopId,
        public readonly string $userId,
        public readonly string $channelId,
    ) {}
}
