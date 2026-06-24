<?php

namespace App\NodeConfig;

use Illuminate\Support\Collection;

/**
 * Validates a typed node config against the editor's execution limits.
 */
final class NodeValidator
{
    private const MAX_TIMEOUT_SECONDS = 300;

    private const MAX_RETRIES = 5;

    /**
     * @return Collection<int, string>
     */
    public function violations(NodeConfig $config): Collection
    {
        return collect([
            $config->timeoutSeconds > self::MAX_TIMEOUT_SECONDS
                ? "Timeout {$config->timeoutSeconds}s exceeds the {$config->label} limit."
                : null,
            $config->retries > self::MAX_RETRIES
                ? "Retries {$config->retries} exceeds the {$config->label} limit."
                : null,
        ])->filter()->values();
    }

    public function passes(NodeConfig $config): bool
    {
        return $this->violations($config)->isEmpty();
    }
}
