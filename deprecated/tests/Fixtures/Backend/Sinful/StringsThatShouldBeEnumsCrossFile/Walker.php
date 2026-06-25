<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * Bidirectional suffix-match scenario: `$broadcastVerb` ends with `Verb`,
 * matching the `Verb` enum's short name.
 */
class Walker
{
    public function step(string $broadcastVerb): void
    {
        // intentionally empty
    }
}
