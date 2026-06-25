<?php

namespace App\NodeConfig;

/**
 * Validates a raw node-config array against the editor's execution limits.
 */
final class NodeValidator
{
    private const MAX_TIMEOUT_SECONDS = 300;

    private const MAX_RETRIES = 5;

    /**
     * SYMPTOM #2: validation re-reads the SAME keys from the SAME untyped array
     * with the SAME coalesce defaults duplicated yet again, because nothing
     * upstream ever normalized the bag into a type.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function violations(array $config): array
    {
        $timeout = $config['timeout'] ?? 30;
        $retries = $config['retries'] ?? 0;
        $label = $config['label'] ?? 'Untitled node';

        if ($label === '') {
            $label = 'Untitled node';
        }

        $violations = [];

        if ($timeout > self::MAX_TIMEOUT_SECONDS) {
            $violations[] = "Timeout {$timeout}s exceeds the {$label} limit.";
        }

        if ($retries > self::MAX_RETRIES) {
            $violations[] = "Retries {$retries} exceeds the {$label} limit.";
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function passes(array $config): bool
    {
        return count($this->violations($config)) === 0;
    }
}
