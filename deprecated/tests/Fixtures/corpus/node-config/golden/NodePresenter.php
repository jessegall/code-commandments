<?php

namespace App\NodeConfig;

/**
 * Renders a typed node config into the summary line the editor sidebar shows.
 */
final class NodePresenter
{
    public function summary(NodeConfig $config): string
    {
        $retryNote = $config->allowsRetry()
            ? "{$config->retries} retries"
            : 'no retries';

        return "{$config->label} — {$config->timeoutSeconds}s timeout, {$retryNote}";
    }
}
