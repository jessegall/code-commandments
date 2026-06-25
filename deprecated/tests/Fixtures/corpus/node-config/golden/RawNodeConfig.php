<?php

namespace App\NodeConfig;

use Illuminate\Support\Fluent;

/**
 * The raw, untyped node-config bag exactly as it arrives from the editor payload.
 */
final class RawNodeConfig extends Fluent
{
    /** The configured timeout in seconds, falling back to the supplied default. */
    public function timeoutSecondsOr(int $default): int
    {
        return $this->integer('timeout', $default);
    }

    /** The configured retry count, falling back to the supplied default. */
    public function retriesOr(int $default): int
    {
        return $this->integer('retries', $default);
    }

    /** The configured label, falling back to the supplied default. */
    public function labelOr(string $default): string
    {
        $label = $this->string('label');

        return $label === '' ? $default : $label;
    }
}
