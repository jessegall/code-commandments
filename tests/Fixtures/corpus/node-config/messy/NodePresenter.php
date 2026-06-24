<?php

namespace App\NodeConfig;

/**
 * Renders a raw node-config array into the summary line the editor sidebar shows.
 */
final class NodePresenter
{
    /**
     * SYMPTOM #3: a THIRD copy of the same key reads + coalesce defaults, plus a
     * re-derived "allows retry" rule, because there is no typed config to ask.
     *
     * @param  array<string, mixed>  $config
     */
    public function summary(array $config): string
    {
        $timeout = $config['timeout'] ?? 30;
        $retries = $config['retries'] ?? 0;
        $label = $config['label'] ?? 'Untitled node';

        if ($label === '') {
            $label = 'Untitled node';
        }

        $retryNote = $retries > 0
            ? "{$retries} retries"
            : 'no retries';

        return "{$label} — {$timeout}s timeout, {$retryNote}";
    }
}
