<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\CrossFileCallChain;

/**
 * The leaf — does the raw indexing that NoArrayStringIndexingProphet flags.
 * The DTO shouldn't be introduced here; it should be introduced at A.
 */
class C
{
    public function finalize(array $payload): string
    {
        return $payload['nodeId'] . ':' . $payload['port'];
    }
}
