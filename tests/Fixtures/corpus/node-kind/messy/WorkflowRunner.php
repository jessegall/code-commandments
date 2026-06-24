<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * Steps a saved workflow node-by-node — the run path ALSO branches on the raw
 * kind string with its own match ladder, smearing typed behaviour across files.
 */
class WorkflowRunner
{
    public function step(Fluent $node): string
    {
        $kind = (string) ($node->get('kind') ?? '');
        $config = $node->get('config') ?? [];

        return match ($kind) {
            'trigger' => $this->fireTrigger($config),
            'action' => $this->runAction($config),
            'condition' => $this->evaluateCondition($config),
            default => 'skip',
        };
    }

    private function fireTrigger(array $config): string
    {
        return 'awaiting:' . ($config['event'] ?? 'unknown');
    }

    private function runAction(array $config): string
    {
        return 'invoke:' . ($config['tool'] ?? 'noop');
    }

    private function evaluateCondition(array $config): string
    {
        // drifted again: reads 'expression', factory seeds 'expr'
        return 'branch:' . ($config['expression'] ?? 'true');
    }
}
