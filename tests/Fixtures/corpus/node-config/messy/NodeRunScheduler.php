<?php

namespace App\NodeConfig;

/**
 * Schedules an expanded node for execution by the workflow run executor.
 */
final class NodeRunScheduler
{
    /**
     * SYMPTOM #4: the leaf. Because NodeExpander returned an untyped array (it had
     * no typed config to build an ExpandedNode from), the executor now string-indexes
     * the expanded bag AND re-applies a defensive coalesce default — the rot has
     * cascaded one layer further from the un-typed boundary.
     *
     * @param  array<string, mixed>  $expanded
     * @return array<string, mixed>
     */
    public function schedule(array $expanded): array
    {
        $timeoutMs = $expanded['timeoutMs'] ?? 30000;
        $maxAttempts = $expanded['maxAttempts'] ?? 1;

        return [
            'node' => $expanded['id'] ?? 'unknown',
            'deadlineMs' => $timeoutMs,
            'attemptsRemaining' => $maxAttempts,
        ];
    }
}
